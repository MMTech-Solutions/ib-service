<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services\Strategies;

use App\Features\Modules\Catalog\Contracts\Strategies\ClosedVolumeRewardActivityProviderStrategyInterface;
use App\Features\Modules\Contracts\Data\V1\ResolveClosedVolumeRewardActivityQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use Carbon\CarbonImmutable;

final class BrokerClosedVolumeRewardActivityProviderStrategy implements ClosedVolumeRewardActivityProviderStrategyInterface
{
    public function __construct(
        private readonly BrokerInstrumentCatalogApiClient $broker,
    ) {}

    public function fetch(ResolveClosedVolumeRewardActivityQueryData $query): VolumeRewardActivityData
    {
        return $this->activity($query->module_id, $this->broker->closedPosition($query->order_id, $query->external_trader_id));
    }

    /** @param array<string, mixed> $item */
    private function activity(string $moduleId, array $item): VolumeRewardActivityData
    {
        foreach (['source_activity_id', 'subject_external_user_id', 'unit_code', 'quantity', 'occurred_at', 'symbol_id', 'server_group_id', 'currency_code', 'broker_granted_commission'] as $field) {
            if (! isset($item[$field]) || ! is_string($item[$field])) {
                throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid closed position.');
            }
        }
        if (! is_int($item['currency_precision'] ?? null)) {
            throw InvalidProgressionActivityQueryException::withMessage('Broker returned an invalid closed position currency precision.');
        }

        return new VolumeRewardActivityData(
            module_id: $moduleId,
            source_activity_id: $item['source_activity_id'], subject_external_user_id: $item['subject_external_user_id'], unit_code: $item['unit_code'], quantity: $item['quantity'],
            occurred_at: CarbonImmutable::parse($item['occurred_at'])->utc()->toIso8601String(), instrument_reference: 'broker:server_group:'.$item['server_group_id'].':symbol:'.$item['symbol_id'],
            currency_code: strtoupper($item['currency_code']), currency_precision: $item['currency_precision'], broker_granted_commission: $item['broker_granted_commission'],
        );
    }
}
