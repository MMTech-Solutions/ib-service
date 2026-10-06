<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services\Strategies;

use App\Features\Modules\Catalog\Contracts\Strategies\CpaEvidenceProviderStrategyInterface;
use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaVolumeEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use Carbon\CarbonImmutable;

final class BrokerCpaEvidenceProviderStrategy implements CpaEvidenceProviderStrategyInterface
{
    public function __construct(
        private readonly BrokerInstrumentCatalogApiClient $broker,

    ) {}

    public function fetch(ListCpaEvidenceQueryData $query): CpaEvidenceData
    {
        $from = CarbonImmutable::parse($query->occurred_from)->utc();
        $until = CarbonImmutable::parse($query->occurred_until)->utc();
        $references = array_values(array_unique(array_map(
            static fn (array $instrument): string => $instrument['symbol_reference'],
            $query->instruments,
        )));
        $allowed = array_fill_keys($references, true);
        $volumeFacts = [];
        $cursor = null;
        do {
            $page = $this->broker->progressionActivities([
                'from' => $from->toIso8601String(),
                'until' => $until->toIso8601String(),
                'external_user_id' => $query->subject_external_user_id,
                'instrument_references' => $references,
                'limit' => 200,
                'cursor' => $cursor,
            ]);
            foreach ($page['data'] as $item) {
                $activity = $this->activity($item);
                if (isset($allowed[$activity->instrument_reference ?? ''])) {
                    $volumeFacts[] = $activity;
                }
            }
            $next = $page['meta']['next_cursor'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
        } while ($cursor !== null);

        return new CpaEvidenceData($volumeFacts, []);
    }

    /** @param array<string, mixed> $item */
    private function activity(array $item): CpaVolumeEvidenceData
    {
        foreach (['source_activity_id', 'subject_external_user_id', 'metric_code', 'unit_code', 'quantity', 'occurred_at', 'symbol_id', 'server_group_id'] as $field) {
            if (! isset($item[$field]) || ! is_string($item[$field])) {
                throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid CPA activity.');
            }
        }

        return new CpaVolumeEvidenceData(
            $item['source_activity_id'],
            $item['subject_external_user_id'],
            $item['metric_code'],
            $item['unit_code'],
            $item['quantity'],
            CarbonImmutable::parse($item['occurred_at'])->utc()->toIso8601String(),
            'broker:server_group:'.$item['server_group_id'].':symbol:'.$item['symbol_id'],
        );
    }
}
