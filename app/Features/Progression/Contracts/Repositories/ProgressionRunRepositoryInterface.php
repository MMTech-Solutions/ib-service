<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Repositories;

use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use Carbon\CarbonImmutable;
use Closure;

interface ProgressionRunRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function latestWindowEndsAt(string $planId): ?CarbonImmutable;

    public function findOrCreateRun(string $planId, ProgressionWindow $window, CarbonImmutable $now): ProgressionRun;

    public function findOrCreateResult(string $runId, string $subscriptionId, CarbonImmutable $now): ProgressionRunResult;

    public function sumAcceptedContributionPoints(string $planId, string $subscriptionId, ProgressionWindow $window): ExactDecimal;

    public function markSkipped(ProgressionRunResult $result, CarbonImmutable $now): void;

    public function markCompleted(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void;

    public function markFailed(ProgressionRunResult $result, string $message, CarbonImmutable $now): void;

    public function finishRun(ProgressionRun $run, CarbonImmutable $now): ProgressionRun;

    /** @return list<ProgressionRunResult> */
    public function finalizedResultsAwaitingPlacement(): array;

    public function lockFinalizedResultAwaitingPlacement(string $resultId): ?ProgressionRunResult;

    public function recordPlacementApplication(string $resultId, ProgressionPlacementOutcome $outcome, CarbonImmutable $now): void;
}
