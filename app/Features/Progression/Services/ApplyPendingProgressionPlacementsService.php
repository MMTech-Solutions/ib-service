<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

use App\Features\Progression\Contracts\Ports\Output\ProgressionFailurePort;
use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Subscriptions\Contracts\Data\V1\ApplyProgressionPlacementData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class ApplyPendingProgressionPlacementsService
{
    public function __construct(private readonly ProgressionInterFeatureGateways $gateways, private readonly ProgressionFailurePort $failures, private readonly ProgressionExecutionEvidence $evidence, private readonly PrepareProgressionRecoveryService $recovery) {}

    /**
     * @return array{applied: int, unchanged: int, fixed: int, not_active: int, failed: int}
     */
    public function execute(ProgressionRunRepositoryInterface $repository, CarbonImmutable $now, ?string $runId = null, string $operation = 'recover', ?PrepareProgressionRecoveryService $recovery = null): array
    {
        if (config('progression.repository') !== 'postgresql' || config('subscriptions.repository') !== 'postgresql') {
            throw new LogicException('Progression placement application requires PostgreSQL repositories.');
        }

        if ($operation === 'recover' && $recovery === null) {
            $recovery = $this->recovery;
            $recovery->begin();
        }

        $counts = ['applied' => 0, 'unchanged' => 0, 'fixed' => 0, 'not_active' => 0, 'failed' => 0];

        foreach ($repository->finalizedResultsAwaitingPlacement($runId) as $candidate) {
            $this->evidence->id('run_ids', $candidate->runId);
            $this->evidence->id('result_ids', $candidate->id);
            $attempt = null;
            try {
                if ($operation === 'recover') {
                    $attempt = $recovery->execute($repository, $candidate, $now, 'placement');
                    if ($attempt->failure_code !== null) {
                        throw new LogicException('Recovery evidence unavailable.');
                    }
                }
                $this->failures->check($operation, 'before_placement', $candidate->subscriptionId, $candidate->id);
                $outcome = null;
                $repository->transaction(function () use ($repository, $candidate, $now, &$outcome, $attempt): void {
                    $result = $repository->lockFinalizedResultAwaitingPlacement($candidate->id);
                    if ($result === null || $result->targetProgramId === null) {
                        return;
                    }

                    $outcome = $this->gateways->placement()->apply(new ApplyProgressionPlacementData(
                        $result->subscriptionId,
                        $result->targetProgramId,
                        $result->id,
                        $now->toISOString(),
                    ));
                    $repository->recordPlacementApplication($result->id, $outcome, $now);
                    if ($attempt !== null) {
                        $repository->finishRecoveryAttempt($result->id, $attempt->id, 'placement', $outcome->value, null);
                    }
                });

                if ($outcome === null) {
                    continue;
                }

                $counts[$outcome->value]++;
                $this->evidence->count('placements_'.$outcome->value);
                Log::info('progression.placement.applied', [
                    'run_result_id' => $candidate->id,
                    'outcome' => $outcome->value,
                ]);
            } catch (Throwable $throwable) {
                if ($attempt !== null) {
                    $repository->finishRecoveryAttempt($candidate->id, $attempt->id, 'placement', 'failed', $attempt->failure_code ?? 'retryable_failure');
                }
                $repository->recordPlacementFailure($candidate->id, $now);
                $counts['failed']++;
                $this->evidence->count('placements_failed');
                Log::warning('progression.placement.retryable_failure', [
                    'run_result_id' => $candidate->id,
                    'exception_class' => $throwable::class,
                ]);
            }
        }

        return $counts;
    }
}
