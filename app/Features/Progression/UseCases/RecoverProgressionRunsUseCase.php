<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\Contracts\Ports\Output\ProgressionFailurePort;
use App\Features\Progression\DTOs\RecoverProgressionRunsResultData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Services\ApplyPendingProgressionPlacementsService;
use App\Features\Progression\Services\PrepareProgressionRunService;
use App\Features\Progression\Services\ProgressionExecutionEvidence;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecoverProgressionRunsUseCase
{
    public function __construct(
        private readonly ProgressionRunRepositoryFactory $repositoryFactory,
        private readonly ProgressionInterFeatureGateways $gateways,
        private readonly ApplyPendingProgressionPlacementsService $placements,
        private readonly PrepareProgressionRunService $preparation,
        private readonly ProgressionFailurePort $failures,
        private readonly ProgressionExecutionEvidence $evidence,
    ) {}

    public function execute(?string $runId = null, ?CarbonImmutable $now = null): RecoverProgressionRunsResultData
    {
        $clock = ($now ?? app(DomainClock::class)->now())->utc();
        $repository = $this->repositoryFactory->make();
        $counts = ['results_recovered' => 0, 'results_failed' => 0, 'runs_completed' => 0];
        $runs = [];
        foreach ($repository->incompleteRuns($runId) as $run) {
            $this->evidence->id('run_ids', $run->id);
            $this->preparation->execute($repository, $run, $clock);
            $runs[$run->id] = $run;
        }

        foreach ($repository->failedResults($runId) as $retry) {
            if ($retry->run->isCompleted()) {
                continue;
            }
            $this->evidence->id('run_ids', $retry->run->id);
            $this->evidence->id('result_ids', $retry->result->id);
            try {
                $snapshot = $this->preparation->execute($repository, $retry->run, $clock);
                $participant = collect($snapshot->participants)->firstWhere('subscription_id', $retry->result->subscriptionId);
                if ($participant === null) {
                    throw new \LogicException('Run participant missing from snapshot.');
                }
                if (! $participant['is_evaluable']) {
                    $repository->markSkipped($retry->result, $clock);
                } else {
                    $points = $retry->result->totalPoints ?? ExactDecimal::fromString($participant['total_points']);
                    $target = $retry->result->targetProgramId ?? $this->preparation->target($snapshot, $points->value());
                    $repository->prepareDecision($retry->result, $points, $target, $clock);
                    $this->failures->check('recover', 'before_result_finalize', $retry->result->subscriptionId, $retry->result->id);
                    $repository->markCompleted($retry->result, $points, $target, $clock);
                }
                $counts['results_recovered']++;
                $this->evidence->count('results_recovered');
                $runs[$retry->run->id] = $retry->run;
                Log::info('progression.result.recovered', [
                    'run_id' => $retry->run->id,
                    'run_result_id' => $retry->result->id,
                    'attempt' => $retry->result->attemptCount + 1,
                ]);
            } catch (Throwable $throwable) {
                $repository->markFailed($retry->result, 'retryable_failure', $clock);
                $counts['results_failed']++;
                $this->evidence->count('results_failed');
                $runs[$retry->run->id] = $retry->run;
                Log::warning('progression.result.retryable_failure', [
                    'run_id' => $retry->run->id,
                    'run_result_id' => $retry->result->id,
                    'attempt' => $retry->result->attemptCount + 1,
                    'exception_class' => $throwable::class,
                ]);
            }
        }

        foreach ($runs as $run) {
            if ($repository->finishRun($run, $clock)->isCompleted()) {
                $counts['runs_completed']++;
                $this->evidence->count('runs_completed');
            }
        }

        $placementCounts = $this->placements->execute($repository, $clock, $runId);
        Log::info('progression.recover_runs.completed', [
            'run_id' => $runId,
            ...$counts,
            'placements_applied' => $placementCounts['applied'],
            'placements_unchanged' => $placementCounts['unchanged'],
            'placements_fixed' => $placementCounts['fixed'],
            'placements_not_active' => $placementCounts['not_active'],
            'placements_failed' => $placementCounts['failed'],
        ]);

        return new RecoverProgressionRunsResultData(
            $counts['results_recovered'],
            $counts['results_failed'],
            $counts['runs_completed'],
            $placementCounts['applied'],
            $placementCounts['unchanged'],
            $placementCounts['fixed'],
            $placementCounts['not_active'],
            $placementCounts['failed'],
        );
    }
}
