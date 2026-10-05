<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Progression\Contracts\Ports\Output\ProgressionFailurePort;
use App\Features\Progression\DTOs\RecoverProgressionRunsResultData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Services\ApplyPendingProgressionPlacementsService;
use App\Features\Progression\Services\PrepareProgressionRecoveryService;
use App\Features\Progression\Services\ProgressionExecutionEvidence;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecoverProgressionRunsUseCase
{
    public function __construct(
        private readonly ProgressionRunRepositoryFactory $repositoryFactory,
        private readonly ApplyPendingProgressionPlacementsService $placements,
        private readonly PrepareProgressionRecoveryService $recovery,
        private readonly ProgressionFailurePort $failures,
        private readonly ProgressionExecutionEvidence $evidence,
    ) {}

    public function execute(?string $runId = null, ?CarbonImmutable $now = null): RecoverProgressionRunsResultData
    {
        $clock = ($now ?? app(DomainClock::class)->now())->utc();
        $repository = $this->repositoryFactory->make();
        $counts = ['results_recovered' => 0, 'results_failed' => 0, 'runs_completed' => 0];
        $runs = [];
        $this->recovery->begin();
        foreach ($repository->failedResults($runId) as $retry) {
            if ($retry->run->isCompleted()) {
                continue;
            }
            $this->evidence->id('run_ids', $retry->run->id);
            $this->evidence->id('result_ids', $retry->result->id);
            $attempt = null;
            try {
                $snapshot = $repository->snapshot($retry->run->id);
                $participant = $snapshot === null ? null : collect($snapshot->participants)->firstWhere('subscription_id', $retry->result->subscriptionId);
                if ($participant !== null && ! $participant['is_evaluable']) {
                    $attempt = $this->recovery->execute($repository, $retry->result, $clock, 'finalize');
                    $repository->markSkipped($retry->result, $clock);
                } else {
                    $attempt = $this->recovery->execute($repository, $retry->result, $clock, 'finalize');
                    if ($attempt->failure_code !== null) {
                        throw new \LogicException('Recovery evidence unavailable.');
                    }
                    $this->failures->check('recover', 'before_result_finalize', $retry->result->subscriptionId, $retry->result->id);
                    $repository->markCompleted($retry->result, ExactDecimal::fromString($attempt->total_points), $attempt->target_program_id, $clock);
                    $repository->finishRecoveryAttempt($retry->result->id, $attempt->id, 'finalize', 'completed', null);
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
                if ($attempt !== null) {
                    $repository->finishRecoveryAttempt($retry->result->id, $attempt->id, 'finalize', 'failed', $attempt->failure_code ?? 'retryable_failure');
                }
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

        $placementCounts = $this->placements->execute($repository, $clock, $runId, recovery: $this->recovery);
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
