<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\InMemory;

use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Enums\ProgressionRunResultStatus;
use App\Features\Progression\Enums\ProgressionRunStatus;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\Models\ProgressionRunRetry;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Str;

final class InMemoryProgressionRunRepository implements ProgressionRunRepositoryInterface
{
    public function hasCapturedWindow(string $planId, ProgressionWindow $window): bool
    {
        foreach ($this->runs as $run) {
            if ($run->planId === $planId && $run->window->startsAt->equalTo($window->startsAt) && $run->window->endsAt->equalTo($window->endsAt) && ($run->isCompleted() || isset($this->snapshots[$run->id]))) {
                return true;
            }
        }

        return false;
    }

    public function incompleteRuns(?string $runId = null): array
    {
        return array_values(array_filter($this->runs, static fn ($run): bool => ! $run->isCompleted() && ($runId === null || $run->id === $runId)));
    }

    /** @var array<string, ProgressionRunSnapshotData> */
    private array $snapshots = [];

    public function snapshot(string $runId): ?ProgressionRunSnapshotData
    {
        return $this->snapshots[$runId] ?? null;
    }

    public function saveSnapshot(string $runId, ProgressionRunSnapshotData $snapshot): void
    {
        $this->snapshots[$runId] ??= $snapshot;
    }

    public function contributionIds(string $planId, string $subscriptionId, ProgressionWindow $window): array
    {
        return [];
    }

    public function consistentRead(Closure $callback): mixed
    {
        return $this->transaction($callback);
    }

    public function prepareDecision(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void
    {
        $current = $this->results[$result->runId.'|'.$result->subscriptionId] ?? $result;
        if ($current->totalPoints === null) {
            $this->replaceResult($current, $current->status, $points, $targetProgramId, $current->attemptCount);
        }
    }

    public function recordPlacementFailure(string $resultId, CarbonImmutable $now): void {}

    /** @var array<string, ProgressionRun> */
    private array $runs = [];

    /** @var array<string, ProgressionRunResult> */
    private array $results = [];

    /** @var array<string, ProgressionPlacementOutcome> */
    private array $placementApplications = [];

    public function transaction(Closure $callback): mixed
    {
        $before = [$this->runs, $this->results, $this->placementApplications, $this->snapshots];
        try {
            return $callback();
        } catch (\Throwable $error) {
            [$this->runs, $this->results, $this->placementApplications, $this->snapshots] = $before;
            throw $error;
        }
    }

    public function latestWindowEndsAt(string $planId): ?CarbonImmutable
    {
        $ends = array_filter($this->runs, static fn (ProgressionRun $run): bool => $run->planId === $planId);
        if ($ends === []) {
            return null;
        }
        usort($ends, static fn (ProgressionRun $left, ProgressionRun $right): int => $left->window->endsAt <=> $right->window->endsAt);

        return $ends[array_key_last($ends)]->window->endsAt;
    }

    public function findOrCreateRun(string $planId, ProgressionWindow $window, CarbonImmutable $now): ProgressionRun
    {
        foreach ($this->runs as $run) {
            if ($run->planId === $planId && $run->window->startsAt->equalTo($window->startsAt) && $run->window->endsAt->equalTo($window->endsAt)) {
                return $run;
            }
        }

        $id = (string) Str::uuid7();

        return $this->runs[$id] = new ProgressionRun($id, $planId, $window, ProgressionRunStatus::Pending, $now, null);
    }

    public function findRun(string $runId): ?ProgressionRun
    {
        return $this->runs[$runId] ?? null;
    }

    public function findOrCreateResult(string $runId, string $subscriptionId, CarbonImmutable $now): ProgressionRunResult
    {
        $key = $runId.'|'.$subscriptionId;

        return $this->results[$key] ??= new ProgressionRunResult((string) Str::uuid7(), $runId, $subscriptionId, ProgressionRunResultStatus::Failed, null, null, 0);
    }

    public function sumAcceptedContributionPoints(string $planId, string $subscriptionId, ProgressionWindow $window): ExactDecimal
    {
        return ExactDecimal::fromString('0');
    }

    public function markSkipped(ProgressionRunResult $result, CarbonImmutable $now): void
    {
        $this->replaceResult($result, ProgressionRunResultStatus::Skipped, ExactDecimal::fromString('0'), null, $result->attemptCount + 1);
    }

    public function markCompleted(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void
    {
        $current = $this->results[$result->runId.'|'.$result->subscriptionId] ?? $result;
        $this->replaceResult($current, ProgressionRunResultStatus::Completed, $current->totalPoints ?? $points, $current->targetProgramId ?? $targetProgramId, $current->attemptCount + 1);
    }

    public function markFailed(ProgressionRunResult $result, string $message, CarbonImmutable $now): void
    {
        $current = $this->results[$result->runId.'|'.$result->subscriptionId];
        $this->replaceResult($current, ProgressionRunResultStatus::Failed, $current->totalPoints, $current->targetProgramId, $current->attemptCount + 1);
    }

    public function finishRun(ProgressionRun $run, CarbonImmutable $now): ProgressionRun
    {
        if (($this->runs[$run->id] ?? $run)->isCompleted()) {
            return $this->runs[$run->id] ?? $run;
        }
        $failed = array_filter($this->results, static fn (ProgressionRunResult $result): bool => $result->runId === $run->id && $result->status === ProgressionRunResultStatus::Failed);
        $status = $failed === [] ? ProgressionRunStatus::Completed : ProgressionRunStatus::CompletedWithErrors;

        return $this->runs[$run->id] = new ProgressionRun($run->id, $run->planId, $run->window, $status, $run->startedAt, $status === ProgressionRunStatus::Completed ? $now : null);
    }

    public function failedResults(?string $runId = null): array
    {
        return array_values(array_filter(array_map(function (ProgressionRunResult $result) use ($runId): ?ProgressionRunRetry {
            if ($result->status !== ProgressionRunResultStatus::Failed || ($runId !== null && $result->runId !== $runId)) {
                return null;
            }

            $run = $this->findRun($result->runId);

            return $run === null ? null : new ProgressionRunRetry($run, $result);
        }, $this->results)));
    }

    public function finalizedResultsAwaitingPlacement(?string $runId = null): array
    {
        return array_values(array_filter($this->results, fn (ProgressionRunResult $result): bool => $result->status === ProgressionRunResultStatus::Completed && ($runId === null || $result->runId === $runId) && ! isset($this->placementApplications[$result->id])));
    }

    public function lockFinalizedResultAwaitingPlacement(string $resultId): ?ProgressionRunResult
    {
        foreach ($this->results as $result) {
            if ($result->id === $resultId && $result->status === ProgressionRunResultStatus::Completed && $result->targetProgramId !== null && ! isset($this->placementApplications[$resultId])) {
                return $result;
            }
        }

        return null;
    }

    public function recordPlacementApplication(string $resultId, ProgressionPlacementOutcome $outcome, CarbonImmutable $now): void
    {
        $this->placementApplications[$resultId] = $outcome;
    }

    private function replaceResult(ProgressionRunResult $result, ProgressionRunResultStatus $status, ?ExactDecimal $points, ?string $programId, int $attempts): void
    {
        if (($this->results[$result->runId.'|'.$result->subscriptionId] ?? $result)->isFinal()) {
            return;
        }
        $this->results[$result->runId.'|'.$result->subscriptionId] = new ProgressionRunResult($result->id, $result->runId, $result->subscriptionId, $status, $points, $programId, $attempts);
    }
}
