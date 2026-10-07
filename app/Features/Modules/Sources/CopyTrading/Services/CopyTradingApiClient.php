<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services;

use App\Features\Modules\Contracts\Exceptions\ActivityProviderUnavailableException;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CopyTradingApiClient
{
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
