<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Exceptions\NegativePnlPeriodsUnavailableException;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Illuminate\Support\Facades\Http;
use Throwable;

final class CopyTradingNegativePnlApiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: list<array<string, mixed>>, meta: array{completed_subjects: list<string>}}
     */
    public function resolve(array $payload): array
    {
        $settings = app(ResolveSettingsPort::class)->execute(['modules.sources.copy_trading.base_url', 'modules.sources.copy_trading.timeout_seconds', 'modules.sources.copy_trading.internal_token', 'modules.sources.copy_trading.source_service', 'modules.sources.copy_trading.internal_prefix']);
        try {
            $response = Http::baseUrl((string) $settings->get('modules.sources.copy_trading.base_url'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) $settings->get('modules.sources.copy_trading.timeout_seconds'))
                ->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) $settings->get('modules.sources.copy_trading.internal_token'),
                    'X-Internal-Source' => (string) $settings->get('modules.sources.copy_trading.source_service'),
                ])
                ->post((string) $settings->get('modules.sources.copy_trading.internal_prefix').'/accounts/negative-pnl-periods/resolve', $payload);
        } catch (Throwable) {
            throw NegativePnlPeriodsUnavailableException::create();
        }

        if ($response->status() === 409 || $response->serverError()) {
            throw NegativePnlPeriodsUnavailableException::create();
        }

        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ! is_array($body['data'] ?? null) || ! array_is_list($body['data'])) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }

        if (! is_array($body['meta']['completed_subjects'] ?? null)) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }

        return ['data' => array_values($body['data']), 'meta' => $body['meta']];
    }
}
