<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services;

use App\Features\Modules\Contracts\Exceptions\BrokerInstrumentCatalogUnavailableException;
use App\Features\Modules\Contracts\Exceptions\BrokerProgressionActivityUnavailableException;
use Illuminate\Support\Facades\Http;

final class BrokerInstrumentCatalogApiClient
{
    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function list(string $type, array $query): array
    {
        try {
            $response = Http::baseUrl((string) config('modules.sources.broker.base_url'))->acceptJson()->timeout((int) config('modules.sources.broker.timeout_seconds'))->connectTimeout(3)->withHeaders([
                'X-Internal-Token' => (string) config('modules.sources.broker.internal_token'),
                'X-Internal-Source' => (string) config('modules.sources.broker.source_service'),
            ])->get((string) config('modules.sources.broker.internal_prefix').'/instrument-catalog/'.$type, $query);
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
            $response = Http::baseUrl((string) config('modules.sources.broker.base_url'))->acceptJson()
                ->timeout((int) config('modules.activity.timeout_seconds', 5))->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) config('modules.sources.broker.internal_token'),
                    'X-Internal-Source' => (string) config('modules.sources.broker.source_service'),
                ])->get((string) config('modules.sources.broker.internal_prefix').'/progression-activities', $query);
        } catch (\Throwable) {
            throw BrokerProgressionActivityUnavailableException::create();
        }

        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
            throw BrokerProgressionActivityUnavailableException::create();
        }

        return ['data' => $body['data'], 'meta' => is_array($body['meta'] ?? null) ? $body['meta'] : []];
    }
}
