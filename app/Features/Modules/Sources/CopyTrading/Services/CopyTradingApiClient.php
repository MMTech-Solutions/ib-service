<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services;

use App\Features\Modules\Contracts\Exceptions\ActivityProviderUnavailableException;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CopyTradingApiClient
{
    /** @param array<string, mixed> $query @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function progressionActivities(array $query): array
    {
        try {
            $page = $this->get('/progression-activities', $query);
        } catch (InvalidVolumeRewardActivityException) {
            throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned invalid activity evidence.');
        }
        if (! array_key_exists('next_cursor', $page['meta']) || ($page['meta']['next_cursor'] !== null && (! is_string($page['meta']['next_cursor']) || $page['meta']['next_cursor'] === ''))) {
            throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned invalid activity evidence.');
        }
        $seen = [];
        foreach ($page['data'] as $item) {
            foreach (['source_activity_id', 'subject_external_user_id', 'metric_code', 'unit_code', 'quantity', 'occurred_at', 'server_group_id', 'symbol_id'] as $key) {
                if (! is_string($item[$key] ?? null) || trim($item[$key]) === '') {
                    throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned invalid activity evidence.');
                }
            }
            if (! str_starts_with($item['source_activity_id'], 'copy_trading:position:') || isset($seen[$item['source_activity_id']])
                || $item['metric_code'] !== 'closed_trading_volume' || $item['unit_code'] !== 'lot'
                || preg_match('/^\d{1,20}(\.\d{1,8})?$/D', $item['quantity']) !== 1
                || str_contains($item['server_group_id'], ':') || str_contains($item['symbol_id'], ':')
                || (isset($query['external_user_id']) && $item['subject_external_user_id'] !== $query['external_user_id'])) {
                throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned invalid activity evidence.');
            }
            try {
                $occurred = CarbonImmutable::parse($item['occurred_at']);
                if ($occurred->lt(CarbonImmutable::parse($query['from'])) || ! $occurred->lt(CarbonImmutable::parse($query['until']))) {
                    throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned invalid activity evidence.');
                }
            } catch (Throwable) {
                throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned invalid activity evidence.');
            }
            $seen[$item['source_activity_id']] = true;
        }

        return $page;
    }

    /** @param array<string, mixed> $query @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function get(string $path, array $query): array
    {
        $settings = app(ResolveSettingsPort::class)->execute(['modules.sources.copy_trading.base_url', 'modules.sources.copy_trading.timeout_seconds', 'modules.sources.copy_trading.internal_token', 'modules.sources.copy_trading.source_service', 'modules.sources.copy_trading.internal_prefix']);
        $baseUrl = (string) $settings->get('modules.sources.copy_trading.base_url');
        if ($baseUrl === '') {
            throw ActivityProviderUnavailableException::create();
        }
        try {
            $response = Http::baseUrl($baseUrl)->acceptJson()->timeout((int) $settings->get('modules.sources.copy_trading.timeout_seconds'))->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) $settings->get('modules.sources.copy_trading.internal_token'),
                    'X-Internal-Source' => (string) $settings->get('modules.sources.copy_trading.source_service'),
                ])->get((string) $settings->get('modules.sources.copy_trading.internal_prefix').$path, $query);
        } catch (Throwable) {
            throw ActivityProviderUnavailableException::create();
        }
        if (! $response->successful()) {
            throw ActivityProviderUnavailableException::create();
        }
        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? null) !== true || ! is_array($body['data'] ?? null) || ! array_is_list($body['data']) || ! is_array($body['meta'] ?? null)) {
            throw InvalidVolumeRewardActivityException::create();
        }

        return ['data' => $body['data'], 'meta' => $body['meta']];
    }
}
