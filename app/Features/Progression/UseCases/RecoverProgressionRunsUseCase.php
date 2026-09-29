<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Programs\Contracts\Data\V1\ResolveProgressionTargetProgramQueryData;
use App\Features\Progression\DTOs\RecoverProgressionRunsResultData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Services\ApplyPendingProgressionPlacementsService;
use App\Features\Progression\Services\ProgressionInterFeatureGateways;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecoverProgressionRunsUseCase
{
    public function __construct(
        private readonly ProgressionRunRepositoryFactory $repositoryFactory,
        private readonly ProgressionInterFeatureGateways $gateways,
        private readonly ApplyPendingProgressionPlacementsService $placements,
    ) {}

    public function execute(?string $runId = null, ?CarbonImmutable $now = null): RecoverProgressionRunsResultData
    {
        $clock = ($now ?? CarbonImmutable::now('UTC'))->utc();
        $repository = $this->repositoryFactory->make();
        $counts = ['results_recovered' => 0, 'results_failed' => 0, 'runs_completed' => 0];
        $runs = [];

        foreach ($repository->failedResults($runId) as $retry) {
            try {
                $points = $repository->sumAcceptedContributionPoints(
                    $retry->run->planId,
                    $retry->result->subscriptionId,
                    $retry->run->window,
                );
                $target = $this->gateways->targetProgram()->resolve(new ResolveProgressionTargetProgramQueryData(
                    $retry->run->planId,
                    $points->value(),
                ));
                $repository->markCompleted($retry->result, $points, $target->program_id, $clock);
                $counts['results_recovered']++;
                $runs[$retry->run->id] = $retry->run;
                Log::info('progression.result.recovered', [
                    'run_id' => $retry->run->id,
                    'run_result_id' => $retry->result->id,
                    'attempt' => $retry->result->attemptCount + 1,
                ]);
            } catch (Throwable $throwable) {
                $repository->markFailed($retry->result, 'retryable_failure', $clock);
                $counts['results_failed']++;
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
