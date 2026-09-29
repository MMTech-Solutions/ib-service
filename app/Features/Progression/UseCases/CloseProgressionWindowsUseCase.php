<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Programs\Contracts\Data\V1\ResolveProgressionTargetProgramQueryData;
use App\Features\Progression\DTOs\CloseProgressionWindowsResultData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use App\Features\Progression\Support\DeriveProgressionWindowFromPeriod;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Contracts\Data\V1\ApplyProgressionPlacementData;
use App\Features\Subscriptions\Contracts\Data\V1\ListProgressionWindowSubscriptionsQueryData;
use Carbon\CarbonImmutable;

final class CloseProgressionWindowsUseCase
{
    public function __construct(
        private readonly ProgressionRunRepositoryFactory $repositoryFactory,
        private readonly ProgressionInterFeatureGateways $gateways,
        private readonly DeriveProgressionWindowFromPeriod $windows,
    ) {}

    public function execute(?CarbonImmutable $now = null): CloseProgressionWindowsResultData
    {
        $clock = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $eligibleAt = $clock->subHour();
        $counts = ['runs_created' => 0, 'runs_completed' => 0, 'runs_with_errors' => 0, 'results_completed' => 0, 'results_skipped' => 0, 'results_failed' => 0];
        $repository = $this->repositoryFactory->make();

        foreach ($this->gateways->activePlansForProgression()->list() as $plan) {
            $lastEnd = $repository->latestWindowEndsAt($plan->plan_id);
            $window = $lastEnd === null
                ? $this->latestDueWindow($plan->progression_period, $eligibleAt)
                : $this->nextWindow($plan->progression_period, $lastEnd);

            while ($window->endsAt->lessThanOrEqualTo($eligibleAt)) {
                $run = $repository->findOrCreateRun($plan->plan_id, $window, $clock);
                if ($run->isCompleted()) {
                    $window = $this->nextWindow($plan->progression_period, $window->endsAt);

                    continue;
                }
                $counts['runs_created']++;

                foreach ($this->gateways->windowSubscriptions()->list(new ListProgressionWindowSubscriptionsQueryData($plan->plan_id, $window->startsAtIso(), $window->endsAtIso())) as $subscription) {
                    $result = $repository->findOrCreateResult($run->id, $subscription->subscription_id, $clock);
                    if ($result->isFinal()) {
                        continue;
                    }
                    if (! $subscription->is_evaluable) {
                        $repository->markSkipped($result, $clock);
                        $counts['results_skipped']++;

                        continue;
                    }
                    try {
                        $points = $repository->sumAcceptedContributionPoints($plan->plan_id, $subscription->subscription_id, $window);
                        $target = $this->gateways->targetProgram()->resolve(new ResolveProgressionTargetProgramQueryData($plan->plan_id, $points->value()));
                        $repository->markCompleted($result, $points, $target->program_id, $clock);
                        $counts['results_completed']++;
                    } catch (\Throwable $throwable) {
                        $repository->markFailed($result, $throwable->getMessage(), $clock);
                        report($throwable);
                        $counts['results_failed']++;
                    }
                }

                $finished = $repository->finishRun($run, $clock);
                $counts[$finished->isCompleted() ? 'runs_completed' : 'runs_with_errors']++;
                $window = $this->nextWindow($plan->progression_period, $window->endsAt);
            }
        }

        $this->assertPostgreSqlRepositories();
        foreach ($repository->finalizedResultsAwaitingPlacement() as $candidate) {
            try {
                $repository->transaction(function () use ($repository, $candidate, $clock): void {
                    $result = $repository->lockFinalizedResultAwaitingPlacement($candidate->id);
                    if ($result === null || $result->targetProgramId === null) {
                        return;
                    }

                    $outcome = $this->gateways->placement()->apply(new ApplyProgressionPlacementData($result->subscriptionId, $result->targetProgramId, $result->id, $clock->toISOString()));
                    $repository->recordPlacementApplication($result->id, $outcome, $clock);
                });
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }

        return new CloseProgressionWindowsResultData(...$counts);
    }

    private function nextWindow(string $period, CarbonImmutable $startsAt): ProgressionWindow
    {
        return match ($period) {
            'daily' => ProgressionWindow::of($startsAt, $startsAt->addDay()),
            'weekly' => ProgressionWindow::of($startsAt, $startsAt->addWeek()),
            'monthly' => ProgressionWindow::of($startsAt, $startsAt->addMonthNoOverflow()),
        };
    }

    private function latestDueWindow(string $period, CarbonImmutable $eligibleAt): ProgressionWindow
    {
        $window = $this->windows->derive($period, $eligibleAt);

        while ($window->endsAt->greaterThan($eligibleAt)) {
            $window = $this->windows->derive($period, $window->startsAt->subSecond());
        }

        return $window;
    }

    private function assertPostgreSqlRepositories(): void
    {
        if (config('progression.repository') !== 'postgresql' || config('subscriptions.repository') !== 'postgresql') {
            throw new \LogicException('Progression placement application requires PostgreSQL repositories.');
        }
    }
}
