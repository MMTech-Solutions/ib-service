<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services\Strategies;

use App\Features\Modules\Catalog\Contracts\Strategies\VolumeRewardActivitiesProviderStrategyInterface;
use App\Features\Modules\Catalog\DTOs\VolumeRewardActivitiesPageData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use Carbon\CarbonImmutable;

final class BrokerVolumeRewardActivitiesProviderStrategy implements VolumeRewardActivitiesProviderStrategyInterface
{
    public function __construct(private readonly BrokerInstrumentCatalogApiClient $broker) {}

    public function fetch(ListVolumeRewardActivitiesQueryData $query): VolumeRewardActivitiesPageData
    {
        $page = $this->broker->progressionActivities([
            'from' => $query->occurred_from,
            'until' => $query->occurred_until,
            'instrument_references' => $query->instrument_references,
            'limit' => $query->limit,
            'cursor' => $query->cursor,
        ]);

        return new VolumeRewardActivitiesPageData(
            array_map(fn (array $item): VolumeRewardActivityData => $this->activity($query->module_id, $item), $page['data']),
            is_string($page['meta']['next_cursor'] ?? null) ? $page['meta']['next_cursor'] : null,
        );
    }

    /** @param array<string, mixed> $item */
    private function activity(string $moduleId, array $item): VolumeRewardActivityData
    {
        foreach (['source_activity_id', 'subject_external_user_id', 'metric_code', 'unit_code', 'quantity', 'occurred_at', 'symbol_id', 'server_group_id', 'currency_code', 'broker_granted_commission'] as $field) {
            if (! isset($item[$field]) || ! is_string($item[$field])) {
                throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid volume reward activity.');
            }
        }
        if ($item['metric_code'] !== 'closed_trading_volume') {
            throw InvalidProgressionActivityQueryException::withMessage('Broker returned an unsupported volume reward metric.');
        }

        $precision = $item['currency_precision'] ?? null;
        if (! is_int($precision) || $precision < 0) {
            throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid server group currency precision.');
        }

        return new VolumeRewardActivityData(
            module_id: $moduleId,
            source_activity_id: $item['source_activity_id'],
            subject_external_user_id: $item['subject_external_user_id'],
            unit_code: $item['unit_code'],
            quantity: $item['quantity'],
            occurred_at: CarbonImmutable::parse($item['occurred_at'])->utc()->toIso8601String(),
            instrument_reference: 'broker:server_group:'.$item['server_group_id'].':symbol:'.$item['symbol_id'],
            currency_code: strtoupper($item['currency_code']),
            currency_precision: $precision,
            broker_granted_commission: $item['broker_granted_commission'],
        );
    }
}
