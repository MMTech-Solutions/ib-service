<?php

declare(strict_types=1);

namespace App\Features\Rewards\Repositories\PostgreSql;

use App\Features\Programs\Contracts\Data\V1\NegativePnlModuleConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Rewards\Contracts\Data\V1\RecordNegativePnlClosureData;
use App\Features\Rewards\DTOs\NegativePnlAccountCutData;
use App\Features\Rewards\DTOs\NegativePnlAggregateData;
use App\Features\Rewards\DTOs\NegativePnlFrozenInputsData;
use App\Features\Rewards\DTOs\NegativePnlProcessingPeriodData;
use App\Features\Rewards\DTOs\NegativePnlWorkData;
use App\Features\Rewards\Repositories\NegativePnlProcessingRepositoryInterface;
use App\Features\Rewards\ValueObjects\NegativePnlCadence;
use App\Features\Settings\Contracts\Ports\Input\ResolveSettingsPort;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use RuntimeException;

final class PostgreSqlNegativePnlProcessingRepository implements NegativePnlProcessingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function discoveryCursor(): array
    {
        $row = $this->connection->table('negative_pnl_discovery_cursors')->where('id', 'default')->first();

        return ['configuration_after' => $row?->configuration_after, 'subscription_after' => $row?->subscription_after, 'started_at' => $row?->started_at === null ? null : CarbonImmutable::parse($row->started_at)->utc()->toISOString()];
    }

    public function saveDiscoveryCursor(?string $configurationAfter, ?string $subscriptionAfter, string $startedAt): void
    {
        $this->connection->table('negative_pnl_discovery_cursors')->upsert([['id' => 'default', 'configuration_after' => $configurationAfter, 'subscription_after' => $subscriptionAfter, 'started_at' => $startedAt]], ['id'], ['configuration_after', 'subscription_after', 'started_at']);
    }

    public function finishDiscovery(string $startedAt): void
    {
        $this->connection->table('negative_pnl_pending_closures')->where('closed_at', '<=', $startedAt)->whereNull('discovered_at')->update(['discovered_at' => CarbonImmutable::now('UTC')]);
        $this->connection->table('negative_pnl_pending_closures')->whereNotNull('discovered_at')->whereNull('completed_at')->whereNotExists(fn (Builder $jobs) => $jobs->selectRaw('1')->from('negative_pnl_jobs')->whereColumn('subscription_id', 'negative_pnl_pending_closures.subscription_id')->whereNull('finished_at'))->update(['completed_at' => CarbonImmutable::now('UTC')]);
    }

    public function countPendingClosures(): int
    {
        return $this->connection->table('negative_pnl_pending_closures')->whereNull('completed_at')->count();
    }

    public function transactionForLease(NegativePnlWorkData $work, Closure $callback): void
    {
        $this->guarded($work, $callback);
    }

    public function seed(NegativePnlSubscriptionData $subscription, NegativePnlProgramConfigurationData $configuration, NegativePnlModuleConfigurationData $group): void
    {
        $start = CarbonImmutable::parse($subscription->activated_at)->max(CarbonImmutable::parse($configuration->starts_at))->utc();
        if ($subscription->relevant_from !== null) {
            $start = $start->max(CarbonImmutable::parse($subscription->relevant_from));
        }
        if ($subscription->closed_at !== null && $start->greaterThanOrEqualTo(CarbonImmutable::parse($subscription->closed_at))) {
            return;
        }
        $identity = hash('sha256', json_encode([$subscription->id, $group->module_id, $configuration->cadence], JSON_THROW_ON_ERROR));
        $this->connection->table('negative_pnl_jobs')->insertOrIgnore([
            'id' => (string) Str::uuid7(), 'identity_key' => $identity, 'subscription_id' => $subscription->id,
            'beneficiary_id' => $subscription->external_user_id, 'plan_id' => $subscription->plan_id,
            'module_id' => $group->module_id, 'cadence' => $configuration->cadence,
            'cursor_at' => $start, 'next_cut_at' => NegativePnlCadence::next($configuration->cadence, $start->toISOString()), 'closed_at' => $subscription->closed_at,
        ]);
        $this->connection->table('negative_pnl_jobs')->where('identity_key', $identity)->whereNotNull('finished_at')->where('cursor_at', '<', $start)->where('next_cut_at', '<', $start)->update(['finished_at' => null, 'cursor_at' => $start, 'next_cut_at' => NegativePnlCadence::next($configuration->cadence, $start->toISOString()), 'restart_interval' => false, 'retry_at' => null]);
        if ($subscription->closed_at !== null) {
            $this->connection->table('negative_pnl_jobs')->where('identity_key', $identity)->update(['closed_at' => $subscription->closed_at]);
            $this->connection->table('negative_pnl_jobs')->where('identity_key', $identity)->where('next_cut_at', '>', $subscription->closed_at)->whereNull('finished_at')->update(['next_cut_at' => $subscription->closed_at]);
        }
        if ($subscription->incoming_subscription_id !== null && $subscription->operation_id !== null && $subscription->closed_at !== null) {
            $this->recordClosure(new RecordNegativePnlClosureData($subscription->id, $subscription->incoming_subscription_id, $subscription->operation_id, $subscription->closed_at));
        }
    }

    public function recordClosure(RecordNegativePnlClosureData $data): void
    {
        $this->connection->table('negative_pnl_pending_closures')->insertOrIgnore($data->toArray());
        $this->connection->table('negative_pnl_jobs')->where('subscription_id', $data->subscription_id)->update(['closed_at' => $data->closed_at]);
        $this->connection->table('negative_pnl_jobs')->where('subscription_id', $data->subscription_id)->where('next_cut_at', '>', $data->closed_at)->whereNull('finished_at')->update(['next_cut_at' => $data->closed_at]);
    }

    public function claim(string $at, int $leaseSeconds, array $excluded): ?NegativePnlWorkData
    {
        return $this->connection->transaction(function () use ($at, $leaseSeconds, $excluded): ?NegativePnlWorkData {
            $row = $this->connection->table('negative_pnl_jobs')->whereNull('finished_at')
                ->where(fn (Builder $q) => $q->where('next_cut_at', '<=', $at)->orWhereExists(fn (Builder $periods) => $periods->selectRaw('1')->from('negative_pnl_periods')->whereColumn('job_id', 'negative_pnl_jobs.id')->where('status', '!=', 'completed')))
                ->where(fn (Builder $q) => $q->whereNull('retry_at')->orWhere('retry_at', '<=', $at))
                ->where(fn (Builder $q) => $q->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', $at))
                ->whereNotIn('id', $excluded)->orderByRaw('last_attempt_at ASC NULLS FIRST')->orderBy('id')->lock('for update skip locked')->first();
            if ($row === null) {
                return null;
            }
            $row->lease_token = (string) Str::uuid7();
            $this->connection->table('negative_pnl_jobs')->where('id', $row->id)->update(['lease_token' => $row->lease_token, 'lease_expires_at' => CarbonImmutable::parse($at)->addSeconds($leaseSeconds), 'last_attempt_at' => $at]);

            return $this->work($row);
        });
    }

    public function pendingPeriod(NegativePnlWorkData $work): ?NegativePnlProcessingPeriodData
    {
        $row = $this->connection->table('negative_pnl_periods')->where('job_id', $work->id)->where('status', '!=', 'completed')->orderBy('occurred_until')->first();

        return $row === null ? null : $this->period($row);
    }

    public function beginPeriod(NegativePnlWorkData $work, NegativePnlFrozenInputsData $inputs): NegativePnlProcessingPeriodData
    {
        return $this->guarded($work, function () use ($work, $inputs): NegativePnlProcessingPeriodData {
            $this->connection->table('negative_pnl_periods')->insertOrIgnore(['id' => (string) Str::uuid7(), 'job_id' => $work->id, 'occurred_from' => $work->cursor_at, 'occurred_until' => $work->next_cut_at, 'status' => 'preparing', 'inputs' => json_encode($inputs->toArray(), JSON_THROW_ON_ERROR), 'receipts' => '{}']);

            return $this->period($this->connection->table('negative_pnl_periods')->where('job_id', $work->id)->where('occurred_until', $work->next_cut_at)->firstOrFail());
        });
    }

    public function recordReceipt(NegativePnlWorkData $work, string $periodId, string $referralId, array $periods): void
    {
        $this->guarded($work, function () use ($work, $periodId, $referralId, $periods): void {
            $row = $this->periodRow($work, $periodId);
            $receipts = json_decode($row->receipts, true, 512, JSON_THROW_ON_ERROR);
            if (! array_key_exists($referralId, $receipts)) {
                $receipts[$referralId] = array_map(static fn (NegativePnlAccountCutData $cut): array => $cut->toArray(), $periods);
                $this->connection->table('negative_pnl_periods')->where('id', $periodId)->update(['receipts' => json_encode($receipts, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function ready(NegativePnlWorkData $work, string $periodId): NegativePnlProcessingPeriodData
    {
        return $this->guarded($work, function () use ($work, $periodId): NegativePnlProcessingPeriodData {
            $period = $this->period($this->periodRow($work, $periodId));
            foreach ($period->inputs->referrals as $referral) {
                if (! array_key_exists($referral->external_user_id, $period->receipts)) {
                    throw new RuntimeException('PnL evidence incomplete');
                }
            }
            if ($period->status === 'preparing') {
                $next = NegativePnlCadence::next($work->cadence, $period->occurred_until);
                $closedAt = $this->connection->table('negative_pnl_jobs')->where('id', $work->id)->value('closed_at');
                if ($closedAt !== null) {
                    $next = CarbonImmutable::parse($next)->min(CarbonImmutable::parse($closedAt))->toISOString();
                }
                $this->connection->table('negative_pnl_jobs')->where('id', $work->id)->update(['cursor_at' => $period->occurred_until, 'next_cut_at' => $next, 'restart_interval' => false]);
                $this->connection->table('negative_pnl_periods')->where('id', $periodId)->update(['status' => 'ready']);
            }

            return $this->period($this->periodRow($work, $periodId));
        });
    }

    public function persistReward(NegativePnlWorkData $work, NegativePnlProcessingPeriodData $period, NegativePnlAggregateData $aggregate, PositiveMoney $amount): bool
    {
        return $this->guarded($work, function () use ($work, $period, $aggregate, $amount): bool {
            $configuration = $period->inputs->configuration;
            $subscription = $period->inputs->subscription;
            $key = 'pnl:'.hash('sha256', json_encode([$work->subscription_id, $work->module_id, $period->id, $aggregate->distribution_level, $aggregate->currency_code, $configuration->assignment_id, $configuration->rule_version_id], JSON_THROW_ON_ERROR));
            $id = (string) Str::uuid7();
            $now = CarbonImmutable::now('UTC');
            $created = $this->connection->table('rewards')->insertOrIgnore([
                'id' => $id, 'beneficiary_user_id' => $work->beneficiary_id, 'plan_id' => $work->plan_id, 'program_id' => $subscription->program_id,
                'module_id' => $work->module_id, 'rule_assignment_id' => $configuration->assignment_id, 'rule_id' => $configuration->rule_id, 'rule_version_id' => $configuration->rule_version_id,
                'amount_minor' => $amount->minorUnits, 'currency_code' => $aggregate->currency_code, 'currency_precision' => $aggregate->currency_precision,
                'status' => 'pending', 'commission_type' => 'pnl', 'network_level' => $aggregate->distribution_level,
                'summary_snapshot' => json_encode(['period_id' => $period->id, 'inputs' => $period->inputs->toArray(), 'aggregate' => $aggregate->toArray(), 'distribution_level' => $aggregate->distribution_level, 'amount_minor' => $amount->minorUnits, 'rounding' => 'half_up'], JSON_THROW_ON_ERROR),
                'origin_idempotency_key' => $key, 'settlement_idempotency_key' => 'ib-service:reward:'.$id.':settlement', 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($created === 0) {
                return false;
            }
            foreach ($aggregate->contributions as $cut) {
                $this->connection->table('reward_evidence')->insert(['id' => (string) Str::uuid7(), 'reward_id' => $id, 'evidence_provider' => 'broker_service', 'evidence_type' => 'negative_pnl_period', 'source_activity_id' => $period->id.':'.hash('sha256', $cut->trading_account_id), 'subject_external_user_id' => $cut->external_user_id, 'currency_code' => $cut->currency_code, 'occurred_at' => $cut->occurred_until, 'created_at' => $now, 'updated_at' => $now]);
            }

            return true;
        });
    }

    public function recordAggregateOutcome(NegativePnlWorkData $work, string $periodId, NegativePnlAggregateData $aggregate, string $reason): void
    {
        $this->guarded($work, function () use ($work, $periodId, $aggregate, $reason): void {
            $row = $this->periodRow($work, $periodId);
            $outcomes = json_decode($row->outcomes, true, 512, JSON_THROW_ON_ERROR);
            $outcomes['aggregates'][$aggregate->distribution_level][$aggregate->currency_code] = ['result' => $reason, 'aggregate' => $aggregate->toArray()];
            $this->connection->table('negative_pnl_periods')->where('id', $periodId)->update(['outcomes' => json_encode($outcomes, JSON_THROW_ON_ERROR)]);
        });
    }

    public function complete(NegativePnlWorkData $work, string $periodId): void
    {
        $this->guarded($work, function () use ($work, $periodId): void {
            $period = $this->period($this->periodRow($work, $periodId));
            $this->connection->table('negative_pnl_periods')->where('id', $periodId)->update(['status' => 'completed']);
            $closedAt = $this->connection->table('negative_pnl_jobs')->where('id', $work->id)->value('closed_at');
            $finished = $closedAt !== null && CarbonImmutable::parse($period->occurred_until)->greaterThanOrEqualTo(CarbonImmutable::parse($closedAt));
            $this->connection->table('negative_pnl_jobs')->where('id', $work->id)->update(['lease_token' => null, 'lease_expires_at' => null, 'retry_at' => null, 'error_code' => null, 'finished_at' => $finished ? CarbonImmutable::now('UTC') : null]);
            if ($finished && ! $this->connection->table('negative_pnl_jobs')->where('subscription_id', $work->subscription_id)->whereNull('finished_at')->exists()) {
                $this->connection->table('negative_pnl_pending_closures')->where('subscription_id', $work->subscription_id)->whereNotNull('discovered_at')->update(['completed_at' => CarbonImmutable::now('UTC')]);
            }
        });
    }

    public function recordOutcome(NegativePnlWorkData $work, string $periodId, string $referralId, string $accountId, string $reason): void
    {
        $this->guarded($work, function () use ($work, $periodId, $referralId, $accountId, $reason): void {
            $row = $this->periodRow($work, $periodId);
            $outcomes = json_decode($row->outcomes, true, 512, JSON_THROW_ON_ERROR);
            $outcomes[$referralId][$accountId] = $reason;
            $this->connection->table('negative_pnl_periods')->where('id', $periodId)->update(['outcomes' => json_encode($outcomes, JSON_THROW_ON_ERROR)]);
        });
    }

    public function release(NegativePnlWorkData $work, ?string $error, bool $restartInterval = false, bool $finished = false): void
    {
        $settings = app(ResolveSettingsPort::class)->execute(['rewards.negative_pnl.retry_delay_seconds']);
        $this->ownedLease($work)->update(['lease_token' => null, 'lease_expires_at' => null, 'retry_at' => CarbonImmutable::now('UTC')->addSeconds((int) $settings->get('rewards.negative_pnl.retry_delay_seconds')), 'error_code' => $error, 'restart_interval' => $restartInterval || $work->restart_interval, 'finished_at' => $finished ? CarbonImmutable::now('UTC') : null]);
    }

    public function reset(NegativePnlWorkData $work, string $at): NegativePnlWorkData
    {
        return $this->guarded($work, function () use ($work, $at): NegativePnlWorkData {
            $cut = $work->closed_at === null ? $at : CarbonImmutable::parse($at)->min(CarbonImmutable::parse($work->closed_at))->toISOString();
            $this->connection->table('negative_pnl_jobs')->where('id', $work->id)->update(['cursor_at' => $cut, 'next_cut_at' => $work->closed_at === null ? NegativePnlCadence::next($work->cadence, $cut) : CarbonImmutable::parse(NegativePnlCadence::next($work->cadence, $cut))->min(CarbonImmutable::parse($work->closed_at))->toISOString(), 'restart_interval' => false]);

            return $this->work($this->connection->table('negative_pnl_jobs')->where('id', $work->id)->firstOrFail());
        });
    }

    private function ownedLease(NegativePnlWorkData $work): Builder
    {
        return $this->connection->table('negative_pnl_jobs')->where('id', $work->id)->where('lease_token', $work->lease_token)->where('lease_expires_at', '>', CarbonImmutable::now('UTC'));
    }

    private function guarded(NegativePnlWorkData $work, Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($work, $callback): mixed {
            $lease = $this->ownedLease($work)->lockForUpdate()->first();
            if ($lease === null) {
                throw new RuntimeException('PnL lease expired');
            }
            $result = $callback();
            if (CarbonImmutable::parse($lease->lease_expires_at)->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw new RuntimeException('PnL lease expired');
            }

            return $result;
        });
    }

    private function periodRow(NegativePnlWorkData $work, string $periodId): object
    {
        return $this->connection->table('negative_pnl_periods')->where('job_id', $work->id)->where('id', $periodId)->firstOrFail();
    }

    private function period(object $row): NegativePnlProcessingPeriodData
    {
        $receipts = json_decode($row->receipts, true, 512, JSON_THROW_ON_ERROR);
        foreach ($receipts as $referralId => $cuts) {
            $receipts[$referralId] = array_map(static fn (array $cut): NegativePnlAccountCutData => NegativePnlAccountCutData::from($cut), $cuts);
        }

        return new NegativePnlProcessingPeriodData($row->id, CarbonImmutable::parse($row->occurred_until)->utc()->toISOString(), $row->status, NegativePnlFrozenInputsData::from(json_decode($row->inputs, true, 512, JSON_THROW_ON_ERROR)), $receipts, CarbonImmutable::parse($row->occurred_from)->utc()->toISOString());
    }

    private function work(object $row): NegativePnlWorkData
    {
        return new NegativePnlWorkData($row->id, $row->subscription_id, $row->beneficiary_id, $row->plan_id, $row->module_id, $row->cadence, CarbonImmutable::parse($row->next_cut_at)->utc()->toISOString(), $row->cursor_at === null ? null : CarbonImmutable::parse($row->cursor_at)->utc()->toISOString(), $row->closed_at === null ? null : CarbonImmutable::parse($row->closed_at)->utc()->toISOString(), (bool) $row->restart_interval, $row->lease_token);
    }
}
