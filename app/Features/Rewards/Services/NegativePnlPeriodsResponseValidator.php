<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsResultData;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use Carbon\CarbonImmutable;
use Throwable;

final class NegativePnlPeriodsResponseValidator
{
    /** @param array{data: list<array<string, mixed>>, meta: array{completed_subjects: list<string>}} $response */
    public function validate(ResolveNegativePnlPeriodsQueryData $query, array $response): ResolveNegativePnlPeriodsResultData
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.subject_batch_size']);
        $expected = array_map(static fn ($subject): string => $subject->external_user_id, $query->subjects);
        if ($expected === [] || count($expected) > max(1, (int) $settings->get('rewards.negative_pnl.subject_batch_size'))
            || count(array_unique($expected)) !== count($expected)) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        try {
            $from = CarbonImmutable::parse($query->occurred_from)->utc();
            $until = CarbonImmutable::parse($query->occurred_until)->utc();
        } catch (Throwable) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        if (! $from->lessThan($until) || $until->isFuture()) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        $completed = $response['meta']['completed_subjects'];
        if (! array_is_list($completed) || array_filter($completed, static fn ($id): bool => ! is_string($id)) !== []) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        sort($expected);
        sort($completed);
        if ($expected !== $completed) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        $periods = [];
        $accounts = [];
        $positions = [];
        foreach ($response['data'] as $row) {
            if (! is_array($row)) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            foreach (['trading_account_id', 'external_trader_id', 'external_user_id', 'server_group_id', 'currency_code', 'occurred_from', 'occurred_until', 'npnl'] as $field) {
                if (! is_string($row[$field] ?? null) || $row[$field] === '') {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
            }
            if (! in_array($row['external_user_id'], $expected, true) || isset($accounts[$row['trading_account_id']])
                || preg_match('/^[A-Z]{3}$/', $row['currency_code']) !== 1
                || ! is_int($row['currency_precision'] ?? null) || $row['currency_precision'] < 0 || $row['currency_precision'] > 10
                || preg_match('/^-?\d+(\.\d+)?$/', $row['npnl']) !== 1
                || ! is_array($row['position_ids'] ?? null) || ! array_is_list($row['position_ids'])) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            try {
                $sameInterval = CarbonImmutable::parse($row['occurred_from'])->equalTo($from)
                    && CarbonImmutable::parse($row['occurred_until'])->equalTo($until);
            } catch (Throwable) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            if (! $sameInterval || ($row['position_ids'] === [] && bccomp($row['npnl'], '0', 10) !== 0)) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            foreach ($row['position_ids'] as $id) {
                if (! is_string($id) || $id === '' || isset($positions[$id])) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
                $positions[$id] = true;
            }
            $accounts[$row['trading_account_id']] = true;
            $periods[] = new NegativePnlPeriodData(
                $row['trading_account_id'], $row['external_trader_id'], $row['external_user_id'], $row['server_group_id'],
                $row['currency_code'], $row['currency_precision'], $from->toISOString(), $until->toISOString(),
                $row['npnl'], $row['position_ids'],
            );
        }

        return new ResolveNegativePnlPeriodsResultData($periods, $completed);
    }
}
