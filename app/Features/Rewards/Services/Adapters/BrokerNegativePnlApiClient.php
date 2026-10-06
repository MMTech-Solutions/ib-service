<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Exceptions\NegativePnlPeriodsUnavailableException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class BrokerNegativePnlApiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: list<array<string, mixed>>, meta: array{completed_subjects: list<string>}}
     */
    public function resolve(array $payload): array
    {
        try {
            $response = Http::baseUrl((string) config('broker_catalog.base_url'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('rewards.negative_pnl.broker_timeout_seconds', 15))
                ->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) config('broker_catalog.internal_token'),
                    'X-Internal-Source' => (string) config('broker_catalog.source_service'),
                ])
                ->post('/api/broker/v1/internal/accounts/negative-pnl-periods/resolve', $payload);
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
