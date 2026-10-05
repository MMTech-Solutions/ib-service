<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Repositories;

use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\Models\ProgressionRunRetry;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use Carbon\CarbonImmutable;
use Closure;

interface ProgressionRunRepositoryInterface
{
    /** @return list<ProgressionRun> */
    public function incompleteRuns(?string $runId = null): array;

    public function hasCapturedWindow(string $planId, ProgressionWindow $window): bool;

    public function snapshot(string $runId): ?ProgressionRunSnapshotData;

    public function saveSnapshot(string $runId, ProgressionRunSnapshotData $snapshot): void;

    /** @return list<string> */
    public function contributionIds(string $planId, string $subscriptionId, ProgressionWindow $window): array;

    public function consistentRead(Closure $callback): mixed;

    public function prepareDecision(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void;

    public function recordPlacementFailure(string $resultId, CarbonImmutable $now): void;

    public function transaction(Closure $callback): mixed;

    public function latestWindowEndsAt(string $planId): ?CarbonImmutable;

    public function findOrCreateRun(string $planId, ProgressionWindow $window, CarbonImmutable $now): ProgressionRun;

    public function findRun(string $runId): ?ProgressionRun;

    public function findOrCreateResult(string $runId, string $subscriptionId, CarbonImmutable $now): ProgressionRunResult;

    public function sumAcceptedContributionPoints(string $planId, string $subscriptionId, ProgressionWindow $window): ExactDecimal;

    public function markSkipped(ProgressionRunResult $result, CarbonImmutable $now): void;

    public function markCompleted(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void;

    public function markFailed(ProgressionRunResult $result, string $message, CarbonImmutable $now): void;

    public function finishRun(ProgressionRun $run, CarbonImmutable $now): ProgressionRun;

    /** @return list<ProgressionRunRetry> */
    public function failedResults(?string $runId = null): array;

    /** @return list<ProgressionRunResult> */
    public function finalizedResultsAwaitingPlacement(?string $runId = null): array;

    public function lockFinalizedResultAwaitingPlacement(string $resultId): ?ProgressionRunResult;

    public function recordPlacementApplication(string $resultId, ProgressionPlacementOutcome $outcome, CarbonImmutable $now): void;
}
