<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\ProgressionActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Sources\Contracts\ModuleActivitySourceInterface;
use Carbon\CarbonImmutable;

/**
 * Fixture-backed Broker deposits source for M3. Translates provider fixtures into
 * ProgressionActivityData without exposing provider payloads outside this adapter.
 */
final class FixtureBrokerDepositsActivityAdapter implements ModuleActivitySourceInterface
{
    /**
     * @param  list<array{
     *     source_activity_id: string,
     *     subject_external_user_id: string,
     *     quantity: string,
     *     occurred_at: string,
     *     instrument_reference?: string|null
     * }>  $fixtures
     */
    public function __construct(private readonly array $fixtures = []) {}

    public function capabilityCode(): string
    {
        return 'deposits';
    }

    public function fetchPage(
        string $moduleId,
        CarbonImmutable $occurredFrom,
        CarbonImmutable $occurredUntil,
        ?string $afterOccurredAt,
        ?string $afterSourceActivityId,
        int $limit,
    ): array {
        $rows = [];
        foreach ($this->fixtures !== [] ? $this->fixtures : $this->defaultFixtures() as $fixture) {
            $occurredAt = CarbonImmutable::parse($fixture['occurred_at'])->utc();
            if ($occurredAt->lt($occurredFrom) || ! $occurredAt->lt($occurredUntil)) {
                continue;
            }

            if ($afterOccurredAt !== null && $afterSourceActivityId !== null) {
                $after = CarbonImmutable::parse($afterOccurredAt)->utc();
                if ($occurredAt->lt($after)
                    || ($occurredAt->equalTo($after) && $fixture['source_activity_id'] <= $afterSourceActivityId)) {
                    continue;
                }
            }

            $quantity = $fixture['quantity'];
            $this->assertValidQuantity($quantity);

            $rows[] = new ProgressionActivityData(
                module_id: $moduleId,
                source_activity_id: $fixture['source_activity_id'],
                subject_external_user_id: $fixture['subject_external_user_id'],
                metric_code: 'confirmed_deposit',
                unit_code: 'USD',
                quantity: $quantity,
                occurred_at: $occurredAt->toIso8601String(),
                instrument_reference: $fixture['instrument_reference'] ?? null,
            );
        }

        usort(
            $rows,
            static function (ProgressionActivityData $left, ProgressionActivityData $right): int {
                $byTime = strcmp($left->occurred_at, $right->occurred_at);
                if ($byTime !== 0) {
                    return $byTime;
                }

                return strcmp($left->source_activity_id, $right->source_activity_id);
            },
        );

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return list<array{
     *     source_activity_id: string,
     *     subject_external_user_id: string,
     *     quantity: string,
     *     occurred_at: string,
     *     instrument_reference?: string|null
     * }>
     */
    private function defaultFixtures(): array
    {
        return [
            [
                'source_activity_id' => 'broker-deposit-001',
                'subject_external_user_id' => '11111111-1111-4111-8111-111111111111',
                'quantity' => '100.00000000',
                'occurred_at' => '2026-09-10T10:00:00+00:00',
            ],
            [
                'source_activity_id' => 'broker-deposit-002',
                'subject_external_user_id' => '22222222-2222-4222-8222-222222222222',
                'quantity' => '50.5',
                'occurred_at' => '2026-09-10T11:00:00+00:00',
            ],
            [
                'source_activity_id' => 'broker-deposit-003',
                'subject_external_user_id' => '11111111-1111-4111-8111-111111111111',
                'quantity' => '25.12500000',
                'occurred_at' => '2026-09-11T09:30:00+00:00',
            ],
        ];
    }

    private function assertValidQuantity(string $quantity): void
    {
        if (preg_match('/^-?\d+(\.\d{1,8})?$/', $quantity) !== 1) {
            throw InvalidProgressionActivityQueryException::withMessage(
                'Activity quantity must be a decimal with at most eight fractional digits.',
            );
        }
    }
}
