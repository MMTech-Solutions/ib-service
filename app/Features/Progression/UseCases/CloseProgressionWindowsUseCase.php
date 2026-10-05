<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\Contracts\Ports\Output\ProgressionFailurePort;
use App\Features\Progression\DTOs\CloseProgressionWindowsResultData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Services\ApplyPendingProgressionPlacementsService;
use App\Features\Progression\Services\PrepareProgressionRunService;
use App\Features\Progression\Services\ProgressionExecutionEvidence;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use App\Features\Progression\Support\DeriveProgressionWindowFromPeriod;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

final class CloseProgressionWindowsUseCase
{
    public function __construct(
        private readonly ProgressionRunRepositoryFactory $repositoryFactory,
        private readonly ProgressionInterFeatureGateways $gateways,
        private readonly DeriveProgressionWindowFromPeriod $windows,
        private readonly ApplyPendingProgressionPlacementsService $placements,
        private readonly PrepareProgressionRunService $preparation,
        private readonly ProgressionFailurePort $failures,
        private readonly ProgressionExecutionEvidence $evidence,
    ) {}

    public function execute(?CarbonImmutable $now = null): CloseProgressionWindowsResultData
    {
        $clock = ($now ?? app(DomainClock::class)->now())->utc();
        $eligibleAt = $clock->subHour();
        $counts = ['runs_created' => 0, 'runs_completed' => 0, 'runs_with_errors' => 0, 'results_completed' => 0, 'results_skipped' => 0, 'results_failed' => 0];
        $repository = $this->repositoryFactory->make();

        foreach ($this->gateways->activePlansForProgression()->list() as $plan) {
            $lastEnd = $repository->latestWindowEndsAt($plan->plan_id);
            $window = $lastEnd === null
                ? $this->latestDueWindow($plan->progression_period, $eligibleAt)
                : $this->nextWindow($plan->progression_period, $lastEnd);

            $pendingWindows = array_map(static fn ($run): ProgressionWindow => $run->window,
                array_values(array_filter($repository->incompleteRuns(), static fn ($run): bool => $run->planId === $plan->plan_id && $run->window->endsAt->lessThanOrEqualTo($eligibleAt))));
            $dueWindow = $window;

            while ($pendingWindows !== [] || $dueWindow->endsAt->lessThanOrEqualTo($eligibleAt)) {
                $isPending = $pendingWindows !== [];
                $window = $isPending ? array_shift($pendingWindows) : $dueWindow;
                $run = $repository->findOrCreateRun($plan->plan_id, $window, $clock);
                $this->evidence->id('run_ids', $run->id);
                if ($run->isCompleted()) {
                    if (! $isPending) {
                        $dueWindow = $this->nextWindow($plan->progression_period, $window->endsAt);
                    }

                    continue;
                }
                if (! $isPending) {
                    $counts['runs_created']++;
                    $this->evidence->count('runs_created');
                }
                Log::info('progression.run.processing', [
                    'run_id' => $run->id,
                    'plan_id' => $plan->plan_id,
                    'window_starts_at' => $window->startsAt->toISOString(),
                    'window_ends_at' => $window->endsAt->toISOString(),
                ]);

                $snapshot = $this->preparation->execute($repository, $run, $clock);
                foreach ($snapshot->participants as $subscription) {
                    $result = $repository->findOrCreateResult($run->id, $subscription['subscription_id'], $clock);
                    $this->evidence->id('result_ids', $result->id);
                    if ($result->isFinal()) {
                        continue;
                    }
                    if (! $subscription['is_evaluable']) {
                        $repository->markSkipped($result, $clock);
                        $counts['results_skipped']++;
                        $this->evidence->count('results_skipped');
                        Log::info('progression.result.skipped', [
                            'run_id' => $run->id,
                            'run_result_id' => $result->id,
                        ]);

                        continue;
                    }
                    try {
                        $points = $result->totalPoints ?? ExactDecimal::fromString($subscription['total_points']);
                        $target = $result->targetProgramId ?? $this->preparation->target($snapshot, $points->value());
                        $repository->prepareDecision($result, $points, $target, $clock);
                        $this->failures->check('close', 'before_result_finalize', $result->subscriptionId, $result->id);
                        $repository->markCompleted($result, $points, $target, $clock);
                        $counts['results_completed']++;
                        $this->evidence->count('results_completed');
                        Log::info('progression.result.completed', [
                            'run_id' => $run->id,
                            'run_result_id' => $result->id,
                        ]);
                    } catch (\Throwable $throwable) {
                        $repository->markFailed($result, 'retryable_failure', $clock);
                        Log::warning('progression.result.retryable_failure', [
                            'run_id' => $run->id,
                            'run_result_id' => $result->id,
                            'attempt' => $result->attemptCount + 1,
                            'exception_class' => $throwable::class,
                        ]);
                        $counts['results_failed']++;
                        $this->evidence->count('results_failed');
                    }
                }

                $finished = $repository->finishRun($run, $clock);
                $counts[$finished->isCompleted() ? 'runs_completed' : 'runs_with_errors']++;
                $this->evidence->count($finished->isCompleted() ? 'runs_completed' : 'runs_with_errors');
                if (! $isPending) {
                    $dueWindow = $this->nextWindow($plan->progression_period, $window->endsAt);
                }
            }
        }

        $placementCounts = $this->placements->execute($repository, $clock, operation: 'close');
        Log::info('progression.close_windows.completed', [
            ...$counts,
            'placements_applied' => $placementCounts['applied'],
            'placements_unchanged' => $placementCounts['unchanged'],
            'placements_fixed' => $placementCounts['fixed'],
            'placements_not_active' => $placementCounts['not_active'],
            'placements_failed' => $placementCounts['failed'],
        ]);

        return new CloseProgressionWindowsResultData(...[...$counts,
            'placements_applied' => $placementCounts['applied'], 'placements_unchanged' => $placementCounts['unchanged'],
            'placements_fixed' => $placementCounts['fixed'], 'placements_not_active' => $placementCounts['not_active'], 'placements_failed' => $placementCounts['failed'],
        ]);
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
}
