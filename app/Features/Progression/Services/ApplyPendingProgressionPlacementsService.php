<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Subscriptions\Contracts\Data\V1\ApplyProgressionPlacementData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class ApplyPendingProgressionPlacementsService
{
    public function __construct(private readonly ProgressionInterFeatureGateways $gateways) {}

    /**
     * @return array{applied: int, unchanged: int, fixed: int, not_active: int, failed: int}
     */
    public function execute(ProgressionRunRepositoryInterface $repository, CarbonImmutable $now, ?string $runId = null): array
    {
        if (config('progression.repository') !== 'postgresql' || config('subscriptions.repository') !== 'postgresql') {
            throw new LogicException('Progression placement application requires PostgreSQL repositories.');
        }

        $counts = ['applied' => 0, 'unchanged' => 0, 'fixed' => 0, 'not_active' => 0, 'failed' => 0];

        foreach ($repository->finalizedResultsAwaitingPlacement($runId) as $candidate) {
            try {
                $outcome = null;
                $repository->transaction(function () use ($repository, $candidate, $now, &$outcome): void {
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
                });

                if ($outcome === null) {
                    continue;
                }

                $counts[$outcome->value]++;
                Log::info('progression.placement.applied', [
                    'run_result_id' => $candidate->id,
                    'outcome' => $outcome->value,
                ]);
            } catch (Throwable $throwable) {
                $counts['failed']++;
                Log::warning('progression.placement.retryable_failure', [
                    'run_result_id' => $candidate->id,
                    'exception_class' => $throwable::class,
                ]);
            }
        }

        return $counts;
    }
}
