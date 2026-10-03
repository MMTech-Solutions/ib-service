<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Adapters;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlCashFlowEvidenceData;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsResultData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use Carbon\CarbonImmutable;
use Throwable;

final class BrokerResolveNegativePnlPeriodsAdapter implements ResolveNegativePnlPeriodsPort
{
    private const array EVIDENCE_KEYS = [
        'external_deposits',
        'external_withdrawals',
        'internal_deposits',
        'internal_withdrawals',
    ];

    public function __construct(private readonly BrokerNegativePnlApiClient $client) {}

    public function resolve(ResolveNegativePnlPeriodsQueryData $query): ResolveNegativePnlPeriodsResultData
    {
        $payload = [
            'external_user_id' => $query->external_user_id,
            'baselines' => array_map(static fn ($baseline): array => [
                'account_id' => $baseline->account_id,
                'balance_after' => $baseline->balance_after,
                'occurred_until' => $baseline->occurred_until,
            ], $query->baselines),
        ];
        if ($query->occurred_until !== null) {
            $payload['occurred_until'] = $query->occurred_until;
        }
        $rows = $this->client->resolve($payload);

        $periods = array_map(
            fn (array $row): NegativePnlPeriodData => $this->mapPeriod($row),
            $rows,
        );
        if ($query->occurred_until !== null) {
            $seen = [];
            foreach ($periods as $period) {
                if ($period->external_user_id !== $query->external_user_id
                    || isset($seen[$period->account_id])
                    || $period->balance_read_id === null || $period->balance_read_at === null
                    || ! CarbonImmutable::parse($period->occurred_until)->equalTo(CarbonImmutable::parse($query->occurred_until))) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
                $seen[$period->account_id] = true;
                $baseline = collect($query->baselines)->firstWhere('account_id', $period->account_id);
                if (($baseline === null && ! $period->establishesBaseline())
                    || ($baseline !== null && ($period->establishesBaseline()
                        || ! CarbonImmutable::parse($period->occurred_from)->equalTo(CarbonImmutable::parse($baseline->occurred_until))
                        || bccomp($period->balance_before, $baseline->balance_after, $period->currency_precision) !== 0))) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
            }
            foreach ($query->baselines as $baseline) {
                if (! isset($seen[$baseline->account_id])) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
            }
        }

        return new ResolveNegativePnlPeriodsResultData($periods);
    }

    /** @param array<string, mixed> $row */
    private function mapPeriod(array $row): NegativePnlPeriodData
    {
        foreach ([
            'status', 'account_id', 'external_user_id', 'server_group_id', 'currency_code',
            'currency_precision', 'balance_before', 'balance_after', 'occurred_from', 'occurred_until',
            'deposits', 'withdrawals', 'cash_flow_net', 'net_pnl', 'evidence',
        ] as $field) {
            if (! array_key_exists($field, $row)) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
        }

        if (! in_array($row['status'], ['baseline', 'resolved'], true)
            || ! $this->nonEmptyStrings($row, ['account_id', 'external_user_id', 'server_group_id'])
            || ! is_string($row['currency_code'])
            || preg_match('/^[A-Z]{3}$/', $row['currency_code']) !== 1
            || ! is_int($row['currency_precision'])
            || $row['currency_precision'] < 0
            || $row['currency_precision'] > 10
            || ! $this->decimalFields($row, ['balance_after', 'deposits', 'withdrawals', 'cash_flow_net'])
            || ! is_array($row['evidence'])) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }

        try {
            $occurredUntil = CarbonImmutable::parse((string) $row['occurred_until'])->utc();
            $readAt = isset($row['balance_read_at']) ? CarbonImmutable::parse($row['balance_read_at'])->utc() : null;
        } catch (Throwable) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        if (($row['balance_read_id'] ?? null) !== null
            && (! is_string($row['balance_read_id']) || $row['balance_read_id'] === '' || $readAt === null)) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        if ($readAt !== null && ($readAt->greaterThan($occurredUntil) || ! is_string($row['balance_read_id'] ?? null))) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }

        $isBaseline = $row['status'] === 'baseline';
        $balanceBefore = $row['balance_before'] ?? null;
        $occurredFromValue = $row['occurred_from'] ?? null;
        $netPnl = $row['net_pnl'] ?? null;
        $evidence = $this->mapEvidence($row['evidence']);

        if ($isBaseline) {
            if ($balanceBefore !== null || $occurredFromValue !== null || $netPnl !== null
                || ! $this->isZero($row['deposits'], $row['currency_precision'])
                || ! $this->isZero($row['withdrawals'], $row['currency_precision'])
                || ! $this->isZero($row['cash_flow_net'], $row['currency_precision'])
                || $this->evidenceCount($evidence) !== 0) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            $occurredFrom = null;
        } else {
            if (! $this->decimal($balanceBefore) || ! $this->decimal($netPnl) || ! is_string($occurredFromValue)) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            try {
                $occurredFrom = CarbonImmutable::parse($occurredFromValue)->utc();
            } catch (Throwable) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            if (! $occurredFrom->lessThan($occurredUntil)) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            $cashFlowNet = bcsub($row['deposits'], $row['withdrawals'], $row['currency_precision']);
            $balanceDelta = bcsub($row['balance_after'], $balanceBefore, $row['currency_precision']);
            $expectedPnl = bcsub($balanceDelta, $cashFlowNet, $row['currency_precision']);
            if (bccomp($row['cash_flow_net'], $cashFlowNet, $row['currency_precision']) !== 0
                || bccomp($netPnl, $expectedPnl, $row['currency_precision']) !== 0) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
        }

        return new NegativePnlPeriodData(
            status: $row['status'],
            account_id: $row['account_id'],
            external_user_id: $row['external_user_id'],
            server_group_id: $row['server_group_id'],
            currency_code: $row['currency_code'],
            currency_precision: $row['currency_precision'],
            balance_before: $balanceBefore,
            balance_after: $row['balance_after'],
            occurred_from: $readAt === null ? $occurredFrom?->toIso8601String() : $occurredFrom?->toISOString(),
            occurred_until: $readAt === null ? $occurredUntil->toIso8601String() : $occurredUntil->toISOString(),
            deposits: $row['deposits'],
            withdrawals: $row['withdrawals'],
            cash_flow_net: $row['cash_flow_net'],
            net_pnl: $netPnl,
            evidence: $evidence,
            balance_read_id: $row['balance_read_id'] ?? null,
            balance_read_at: $readAt?->toISOString(),
        );
    }

    /** @param array<string, mixed> $evidence */
    private function mapEvidence(array $evidence): NegativePnlCashFlowEvidenceData
    {
        $mapped = [];
        foreach (self::EVIDENCE_KEYS as $key) {
            $item = $evidence[$key] ?? null;
            if (! is_array($item) || ! is_int($item['count'] ?? null) || $item['count'] < 0
                || ! is_array($item['references'] ?? null)
                || count($item['references']) !== $item['count']
                || array_filter($item['references'], static fn ($reference): bool => ! is_string($reference) || $reference === '') !== []) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            $mapped[$key] = $item;
        }

        return new NegativePnlCashFlowEvidenceData(
            external_deposit_count: $mapped['external_deposits']['count'],
            external_deposit_references: array_values($mapped['external_deposits']['references']),
            external_withdrawal_count: $mapped['external_withdrawals']['count'],
            external_withdrawal_references: array_values($mapped['external_withdrawals']['references']),
            internal_deposit_count: $mapped['internal_deposits']['count'],
            internal_deposit_references: array_values($mapped['internal_deposits']['references']),
            internal_withdrawal_count: $mapped['internal_withdrawals']['count'],
            internal_withdrawal_references: array_values($mapped['internal_withdrawals']['references']),
        );
    }

    /** @param list<string> $fields */
    private function nonEmptyStrings(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! is_string($row[$field] ?? null) || $row[$field] === '') {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $fields */
    private function decimalFields(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! $this->decimal($row[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function decimal(mixed $value): bool
    {
        return is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1;
    }

    private function isZero(string $value, int $precision): bool
    {
        return bccomp($value, '0', $precision) === 0;
    }

    private function evidenceCount(NegativePnlCashFlowEvidenceData $evidence): int
    {
        return $evidence->external_deposit_count
            + $evidence->external_withdrawal_count
            + $evidence->internal_deposit_count
            + $evidence->internal_withdrawal_count;
    }
}
