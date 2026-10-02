<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services;

use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionInvalidResponseException;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionNotReadyException;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionUnavailableException;
use App\Features\Modules\Contracts\Exceptions\BrokerInstrumentCatalogUnavailableException;
use App\Features\Modules\Contracts\Exceptions\BrokerProgressionActivityUnavailableException;
use Illuminate\Support\Facades\Http;

final class BrokerInstrumentCatalogApiClient
{
    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function list(string $type, array $query): array
    {
        try {
            $response = Http::baseUrl((string) config('broker_catalog.base_url'))->acceptJson()->timeout((int) config('broker_catalog.timeout_seconds'))->connectTimeout(3)->withHeaders([
                'X-Internal-Token' => (string) config('broker_catalog.internal_token'),
                'X-Internal-Source' => (string) config('broker_catalog.source_service'),
            ])->get('/api/broker/v1/internal/instrument-catalog/'.$type, $query);
        } catch (\Throwable) {
            throw BrokerInstrumentCatalogUnavailableException::create();
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
            throw BrokerInstrumentCatalogUnavailableException::create();
        }

        return ['data' => $body['data'], 'meta' => is_array($body['meta'] ?? null) ? $body['meta'] : []];
    }

    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function progressionActivities(array $query): array
    {
        try {
            $response = Http::baseUrl((string) config('broker_catalog.base_url'))->acceptJson()
                ->timeout((int) config('modules.activity.timeout_seconds', 5))->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) config('broker_catalog.internal_token'),
                    'X-Internal-Source' => (string) config('broker_catalog.source_service'),
                ])->get('/api/broker/v1/internal/progression-activities', $query);
        } catch (\Throwable) {
            throw BrokerProgressionActivityUnavailableException::create();
        }

        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
            throw BrokerProgressionActivityUnavailableException::create();
        }

        return ['data' => $body['data'], 'meta' => is_array($body['meta'] ?? null) ? $body['meta'] : []];
    }

    /** @return array<string, mixed> */
    public function closedPosition(string $orderId, string $externalTraderId): array
    {
        try {
            $response = Http::baseUrl((string) config('broker_catalog.base_url'))->acceptJson()
                ->timeout((int) config('modules.activity.timeout_seconds', 5))->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) config('broker_catalog.internal_token'),
                    'X-Internal-Source' => (string) config('broker_catalog.source_service'),
                ])->get('/api/broker/v1/internal/closed-positions/resolve', [
                    'order_id' => $orderId,
                    'external_trader_id' => $externalTraderId,
                ]);
        } catch (\Throwable) {
            throw BrokerClosedPositionUnavailableException::create();
        }

        if ($response->status() === 409) {
            throw BrokerClosedPositionNotReadyException::create();
        }
        $body = $response->json();
        if ($response->serverError()) {
            throw BrokerClosedPositionUnavailableException::create();
        }
        if (! $response->successful() || ! is_array($body) || ! is_array($body['data'] ?? null)) {
            throw BrokerClosedPositionInvalidResponseException::create();
        }

        return $body['data'];
    }
}
