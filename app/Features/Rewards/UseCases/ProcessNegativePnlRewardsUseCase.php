<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ListNegativePnlConfigurationsPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveNegativePnlProgramConfigurationPort;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlReferralsPort;
use App\Features\Rewards\DTOs\CaptureNegativePnlCutData;
use App\Features\Rewards\DTOs\NegativePnlAccountCutData;
use App\Features\Rewards\DTOs\NegativePnlFrozenInputsData;
use App\Features\Rewards\DTOs\NegativePnlProcessingPeriodData;
use App\Features\Rewards\DTOs\NegativePnlRewardCalculationData;
use App\Features\Rewards\DTOs\NegativePnlWorkData;
use App\Features\Rewards\Exceptions\HistoricalPnlCoverageUnavailableException;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;
use App\Features\Rewards\Exceptions\InvalidNegativePnlReferralsException;
use App\Features\Rewards\Exceptions\NegativePnlPeriodsUnavailableException;
use App\Features\Rewards\Exceptions\NegativePnlReferralsUnavailableException;
use App\Features\Rewards\Factories\NegativePnlProcessingRepositoryFactory;
use App\Features\Rewards\Factories\NegativePnlRewardCalculationStrategyFactory;
use App\Features\Rewards\Repositories\NegativePnlProcessingRepositoryInterface;
use App\Features\Rewards\Services\CaptureNegativePnlCutService;
use App\Features\Subscriptions\Contracts\Ports\Input\ListNegativePnlSubscriptionSegmentsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ListNegativePnlSubscriptionsPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveNegativePnlSubscriptionContextPort;
use Carbon\CarbonImmutable;
use Throwable;

final class ProcessNegativePnlRewardsUseCase
{
    public function __construct(
        private readonly NegativePnlProcessingRepositoryFactory $repositoryFactory,
        private readonly ListNegativePnlConfigurationsPort $configurations,
        private readonly ResolveNegativePnlProgramConfigurationPort $configuration,
        private readonly ListNegativePnlSubscriptionsPort $subscriptions,
        private readonly ResolveNegativePnlSubscriptionContextPort $subscriptionContext,
        private readonly ResolveNegativePnlReferralsPort $referrals,
        private readonly ResolveNegativePnlPeriodsPort $broker,
        private readonly ResolvePlanSubscriptionContextPort $plans,
        private readonly ResolveModulesPort $modules,
        private readonly CaptureNegativePnlCutService $capture,
        private readonly NegativePnlRewardCalculationStrategyFactory $calculationFactory,
        private readonly ListNegativePnlSubscriptionSegmentsPort $segments,
    ) {}

    /** @return array{contexts: int, periods: int, closures: int, pending_closures: int, rewards: int, errors: int} */
    public function execute(int $limit = 100, int $discoveryLimit = 100): array
    {
        $metrics = ['contexts' => 0, 'periods' => 0, 'closures' => 0, 'pending_closures' => 0, 'rewards' => 0, 'errors' => 0];
        if (! config('rewards.negative_pnl.enabled', false)) {
            return $metrics;
        }
        $repository = $this->repositoryFactory->make();
        $this->discover($repository, max(1, min($discoveryLimit, 1000)));
        $excluded = [];
        for ($index = 0; $index < max(1, min($limit, 1000)); $index++) {
            $work = $repository->claim(CarbonImmutable::now('UTC')->toISOString(), max(1, (int) config('rewards.negative_pnl.claim_lease_seconds', 120)), $excluded);
            if ($work === null) {
                break;
            }
            $metrics['contexts']++;
            try {
                $period = $repository->pendingPeriod($work);
                if (! $this->operational($work)) {
                    $repository->release($work, 'processing_paused', resetBaseline: true);
                    $excluded[] = $work->id;

                    continue;
                }
                if ($period === null) {
                    if ($work->reset_baseline) {
                        $work = $repository->reset($work, CarbonImmutable::now('UTC')->toISOString());
                    } elseif (($resetAt = $this->baselineResetAt($work)) !== null) {
                        $work = $repository->reset($work, $resetAt);
                    }
                    $inputs = $this->inputs($work);
                    if ($inputs === null) {
                        $repository->release($work, 'configuration_not_applicable', resetBaseline: true, finished: true);
                        $excluded[] = $work->id;

                        continue;
                    }
                    $period = $repository->beginPeriod($work, $inputs);
                }
                if ($period->status === 'preparing') {
                    $period = $this->evidence($repository, $work, $period);
                }
                $this->calculate($repository, $work, $period, $metrics['rewards']);
                $repository->complete($work, $period->id);
                $metrics['periods']++;
                if ($work->closed_at !== null && CarbonImmutable::parse($period->occurred_until)->equalTo(CarbonImmutable::parse($work->closed_at))) {
                    $metrics['closures']++;
                }
            } catch (Throwable $exception) {
                $code = match (true) {
                    $exception instanceof HistoricalPnlCoverageUnavailableException => 'historical_coverage_unavailable',
                    $exception instanceof NegativePnlPeriodsUnavailableException, $exception instanceof NegativePnlReferralsUnavailableException => 'evidence_unavailable',
                    $exception instanceof InvalidNegativePnlPeriodsResponseException, $exception instanceof InvalidNegativePnlReferralsException => 'evidence_invalid',
                    default => 'processing_failed',
                };
                $repository->release($work, $code);
                $metrics['errors']++;
                $excluded[] = $work->id;
            }
        }

        $metrics['pending_closures'] = $repository->countPendingClosures();

        return $metrics;
    }

    private function discover(NegativePnlProcessingRepositoryInterface $repository, int $limit): void
    {
        $cursor = $repository->discoveryCursor();
        $startedAt = $cursor['started_at'] ?? CarbonImmutable::now('UTC')->toISOString();
        for ($remaining = $limit; $remaining > 0;) {
            $configuration = $this->configurations->execute($cursor['configuration_after'], 1)[0] ?? null;
            if ($configuration === null) {
                $repository->finishDiscovery($startedAt);
                $repository->saveDiscoveryCursor(null, null, CarbonImmutable::now('UTC')->toISOString());
                break;
            }
            $subscriptions = $this->subscriptions->execute($configuration->program_id, $configuration->starts_at, $configuration->ends_at, $cursor['subscription_after'], $remaining);
            foreach ($subscriptions as $subscription) {
                foreach ($configuration->groups as $group) {
                    $repository->seed($subscription, $configuration, $group);
                }
            }
            $remaining -= max(1, count($subscriptions));
            if ($subscriptions !== []) {
                $cursor['subscription_after'] = $subscriptions[array_key_last($subscriptions)]->id;
            } else {
                $cursor = ['configuration_after' => $configuration->id, 'subscription_after' => null];
            }
            $repository->saveDiscoveryCursor($cursor['configuration_after'], $cursor['subscription_after'], $startedAt);
        }
    }

    private function operational(NegativePnlWorkData $work): bool
    {
        $plan = $this->plans->resolve(new ResolvePlanSubscriptionContextQueryData($work->plan_id));
        $module = $this->modules->findByIds([$work->module_id])[0] ?? null;

        return $plan->is_active && ! $plan->archived && $module !== null && $module->code === 'broker' && $module->is_active && $module->processing_status === 'running';
    }

    private function inputs(NegativePnlWorkData $work): ?NegativePnlFrozenInputsData
    {
        $at = CarbonImmutable::parse($work->next_cut_at);
        if ($work->closed_at !== null && $at->equalTo(CarbonImmutable::parse($work->closed_at))) {
            $at = $at->subMicrosecond();
        }
        $subscription = $this->subscriptionContext->execute($work->subscription_id, $at->toISOString());
        if ($subscription === null) {
            return null;
        }
        if ($subscription->subscription_id !== $work->subscription_id || $subscription->plan_id !== $work->plan_id) {
            throw InvalidNegativePnlPeriodsResponseException::create();
        }
        $configuration = $this->configuration->execute(new ResolveNegativePnlProgramConfigurationQueryData($subscription->program_id, $work->module_id, $work->server_group_id, $at->toISOString()));
        if ($configuration === null || $configuration->cadence !== $work->cadence) {
            return null;
        }
        $group = $configuration->groups[0];
        $depth = max(array_map(static fn ($level): int => $level->distribution_level, $group->levels));

        return new NegativePnlFrozenInputsData($subscription, $group, $configuration->id, (string) config('rewards.minimum_amount_major', '0.01'), $this->referrals->resolve($work->beneficiary_id, $depth), $work->reset_baseline);
    }

    private function evidence(NegativePnlProcessingRepositoryInterface $repository, NegativePnlWorkData $work, NegativePnlProcessingPeriodData $period): NegativePnlProcessingPeriodData
    {
        foreach ($period->inputs->referrals as $referral) {
            if (array_key_exists($referral->external_user_id, $period->receipts)) {
                continue;
            }
            $baselines = $period->inputs->reset_baseline ? [] : $repository->baselines($work->id, $referral->external_user_id);
            $query = new ResolveNegativePnlPeriodsQueryData($referral->external_user_id, $baselines, $period->occurred_until);
            $cuts = [];
            $seen = [];
            foreach ($this->broker->resolve($query)->periods as $cut) {
                if ($cut->server_group_id !== $work->server_group_id) {
                    continue;
                }
                if ($cut->external_user_id !== $referral->external_user_id || isset($seen[$cut->account_id]) || ! CarbonImmutable::parse($cut->occurred_until)->equalTo(CarbonImmutable::parse($period->occurred_until)) || $cut->balance_read_id === null || $cut->balance_read_at === null) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
                $seen[$cut->account_id] = true;
                $baseline = $repository->accountNeedsBaseline($work->id, $referral->external_user_id, $cut) || $period->inputs->reset_baseline;
                $cuts[] = new NegativePnlAccountCutData($cut, $baseline);
            }
            $repository->transactionForLease($work, function () use ($repository, $work, $period, $referral, $query, $cuts): void {
                $frozen = [];
                foreach ($cuts as $accountCut) {
                    $cut = $accountCut->cut;
                    $snapshot = $this->capture->execute(new CaptureNegativePnlCutData($work->module_id, $work->subscription_id, $cut->account_id, $work->server_group_id, $work->cadence, $query), $cut);
                    $frozen[] = new NegativePnlAccountCutData($snapshot->period, $accountCut->requires_baseline);
                }
                $repository->recordReceipt($work, $period->id, $referral->external_user_id, $frozen);
            });
        }

        return $repository->ready($work, $period->id);
    }

    private function calculate(NegativePnlProcessingRepositoryInterface $repository, NegativePnlWorkData $work, NegativePnlProcessingPeriodData $period, int &$created): void
    {
        $strategy = $this->calculationFactory->make('negative_pnl_share');
        $subscription = $period->inputs->subscription;
        foreach ($period->inputs->referrals as $referral) {
            $level = collect($period->inputs->configuration->levels)->firstWhere('distribution_level', $referral->distribution_level);
            if ($level === null) {
                continue;
            }
            foreach ($period->receipts[$referral->external_user_id] as $accountCut) {
                $cut = $accountCut->cut;
                if ($accountCut->requires_baseline || $cut->establishesBaseline() || $cut->net_pnl === null) {
                    $repository->recordOutcome($work, $period->id, $referral->external_user_id, $cut->account_id, 'baseline');

                    continue;
                }
                $amount = $strategy->calculate(new NegativePnlRewardCalculationData($cut->net_pnl, $level->rate, $subscription->personal_rate, $subscription->is_master, $subscription->master_rate, $cut->currency_code, $cut->currency_precision, $period->inputs->minimum_amount_major));
                if ($amount !== null && $repository->persistReward($work, $period, $referral->external_user_id, $referral->distribution_level, $cut, $amount)) {
                    $created++;
                }
                $repository->recordOutcome($work, $period->id, $referral->external_user_id, $cut->account_id, $amount !== null ? 'reward' : (bccomp($cut->net_pnl, '0', $cut->currency_precision) >= 0 ? 'non_negative_pnl' : 'below_minimum_or_rounded_zero'));
            }
        }
    }

    private function baselineResetAt(NegativePnlWorkData $work): ?string
    {
        if ($work->cursor_at === null) {
            return null;
        }
        $from = CarbonImmutable::parse($work->cursor_at);
        $until = CarbonImmutable::parse($work->next_cut_at);
        $intervals = [];
        foreach ($this->segments->execute($work->subscription_id, $from->toISOString(), $until->toISOString()) as $segment) {
            $after = null;
            do {
                $page = $this->configurations->execute($after, 1000, $segment->program_id, $from->toISOString(), $until->toISOString());
                foreach ($page as $configuration) {
                    if ($configuration->cadence !== $work->cadence || ! collect($configuration->groups)->contains(fn ($group): bool => $group->module_id === $work->module_id && $group->server_group_id === $work->server_group_id)) {
                        continue;
                    }
                    $start = CarbonImmutable::parse($configuration->starts_at)->max(CarbonImmutable::parse($segment->starts_at))->max($from);
                    $end = $until;
                    if ($configuration->ends_at !== null) {
                        $end = $end->min(CarbonImmutable::parse($configuration->ends_at));
                    }
                    if ($segment->ends_at !== null) {
                        $end = $end->min(CarbonImmutable::parse($segment->ends_at));
                    }
                    if ($start->lessThanOrEqualTo($end)) {
                        $intervals[] = [$start, $end];
                    }
                }
                $after = $page === [] ? null : $page[array_key_last($page)]->id;
            } while (count($page) === 1000);
        }
        usort($intervals, static fn (array $a, array $b): int => $a[0]->toISOString() <=> $b[0]->toISOString());
        $start = null;
        $end = null;
        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            if ($end === null || $intervalStart->greaterThan($end)) {
                $start = $intervalStart;
            }
            $end = $end === null ? $intervalEnd : $end->max($intervalEnd);
        }

        return $start !== null && $end->equalTo($until) && $start->greaterThan($from) ? $start->toISOString() : null;
    }
}
