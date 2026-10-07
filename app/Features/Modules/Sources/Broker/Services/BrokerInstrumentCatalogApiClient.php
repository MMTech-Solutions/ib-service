<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services;

use App\Features\Modules\Contracts\Exceptions\BrokerInstrumentCatalogUnavailableException;
use App\Features\Modules\Contracts\Exceptions\BrokerProgressionActivityUnavailableException;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Illuminate\Support\Facades\Http;

final class BrokerInstrumentCatalogApiClient
{
    /** @return array{data: list<array<string, mixed>>, meta: array<string, mixed>} */
    public function list(string $type, array $query): array
    {
        $settings = app(ResolveSettingsPort::class)->execute(['modules.sources.broker.base_url', 'modules.sources.broker.timeout_seconds', 'modules.sources.broker.internal_token', 'modules.sources.broker.source_service', 'modules.sources.broker.internal_prefix']);
        try {
            $response = Http::baseUrl((string) $settings->get('modules.sources.broker.base_url'))->acceptJson()->timeout((int) $settings->get('modules.sources.broker.timeout_seconds'))->connectTimeout(3)->withHeaders([
                'X-Internal-Token' => (string) $settings->get('modules.sources.broker.internal_token'),
                'X-Internal-Source' => (string) $settings->get('modules.sources.broker.source_service'),
            ])->get((string) $settings->get('modules.sources.broker.internal_prefix').'/instrument-catalog/'.$type, $query);
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
        $settings = app(ResolveSettingsPort::class)->execute(['modules.sources.broker.base_url', 'modules.activity.timeout_seconds', 'modules.sources.broker.internal_token', 'modules.sources.broker.source_service', 'modules.sources.broker.internal_prefix']);
        try {
            $response = Http::baseUrl((string) $settings->get('modules.sources.broker.base_url'))->acceptJson()
                ->timeout((int) $settings->get('modules.activity.timeout_seconds'))->connectTimeout(3)
                ->withHeaders([
                    'X-Internal-Token' => (string) $settings->get('modules.sources.broker.internal_token'),
                    'X-Internal-Source' => (string) $settings->get('modules.sources.broker.source_service'),
                ])->get((string) $settings->get('modules.sources.broker.internal_prefix').'/progression-activities', $query);
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
