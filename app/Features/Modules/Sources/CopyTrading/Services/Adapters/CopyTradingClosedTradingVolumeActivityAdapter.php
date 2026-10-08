<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\ProgressionActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Sources\Contracts\ModuleActivitySourceInterface;
use App\Features\Modules\Sources\CopyTrading\Services\CopyTradingApiClient;
use Carbon\CarbonImmutable;

final class CopyTradingClosedTradingVolumeActivityAdapter implements ModuleActivitySourceInterface
{
    public function __construct(private readonly CopyTradingApiClient $client) {}

    public function capabilityCode(): string
    {
        return 'closed_trading_volume';
    }

    /** @return list<ProgressionActivityData> */
    public function fetchPage(
        string $moduleId,
        CarbonImmutable $occurredFrom,
        CarbonImmutable $occurredUntil,
        ?string $afterOccurredAt,
        ?string $afterSourceActivityId,
        int $limit,
    ): array {
        $cursor = null;
        if ($afterOccurredAt !== null && $afterSourceActivityId !== null) {
            $cursor = base64_encode(json_encode([
                'closed_at' => CarbonImmutable::parse($afterOccurredAt)->getTimestampMs(),
                'id' => str_replace('copy_trading:position:', '', $afterSourceActivityId),
            ], JSON_THROW_ON_ERROR));
        }
        $response = $this->client->progressionActivities([
            'from' => $occurredFrom->toIso8601String(),
            'until' => $occurredUntil->toIso8601String(),
            'limit' => $limit,
            'cursor' => $cursor,
        ]);

        return array_map(function (array $item) use ($moduleId): ProgressionActivityData {
            foreach (['source_activity_id', 'subject_external_user_id', 'metric_code', 'unit_code', 'quantity', 'occurred_at', 'symbol_id', 'server_group_id'] as $field) {
                if (! isset($item[$field]) || ! is_string($item[$field])) {
                    throw InvalidProgressionActivityQueryException::withMessage('Copy Trading returned an invalid progression activity.');
                }
            }

            return new ProgressionActivityData(
                module_id: $moduleId,
                source_activity_id: $item['source_activity_id'],
                subject_external_user_id: $item['subject_external_user_id'],
                metric_code: $item['metric_code'],
                unit_code: $item['unit_code'],
                quantity: $item['quantity'],
                occurred_at: CarbonImmutable::parse($item['occurred_at'])->utc()->toIso8601String(),
                instrument_reference: 'copy_trading:server_group:'.$item['server_group_id'].':symbol:'.$item['symbol_id'],
            );
        }, $response['data']);
    }
}
