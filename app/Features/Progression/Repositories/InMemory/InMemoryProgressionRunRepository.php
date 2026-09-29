<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\InMemory;

use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\Enums\ProgressionRunResultStatus;
use App\Features\Progression\Enums\ProgressionRunStatus;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class InMemoryProgressionRunRepository implements ProgressionRunRepositoryInterface
{
    /** @var array<string, ProgressionRun> */
    private array $runs = [];

    /** @var array<string, ProgressionRunResult> */
    private array $results = [];

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

        return $this->runs[(string) $id = Str::uuid7()] = new ProgressionRun($id, $planId, $window, ProgressionRunStatus::Pending, $now, null);
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
        $this->replaceResult($result, ProgressionRunResultStatus::Skipped, ExactDecimal::fromString('0'), null, $result->attemptCount);
    }

    public function markCompleted(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void
    {
        $this->replaceResult($result, ProgressionRunResultStatus::Completed, $points, $targetProgramId, $result->attemptCount);
    }

    public function markFailed(ProgressionRunResult $result, string $message, CarbonImmutable $now): void
    {
        $this->replaceResult($result, ProgressionRunResultStatus::Failed, null, null, $result->attemptCount + 1);
    }

    public function finishRun(ProgressionRun $run, CarbonImmutable $now): ProgressionRun
    {
        $failed = array_filter($this->results, static fn (ProgressionRunResult $result): bool => $result->runId === $run->id && $result->status === ProgressionRunResultStatus::Failed);
        $status = $failed === [] ? ProgressionRunStatus::Completed : ProgressionRunStatus::CompletedWithErrors;

        return $this->runs[$run->id] = new ProgressionRun($run->id, $run->planId, $run->window, $status, $run->startedAt, $status === ProgressionRunStatus::Completed ? $now : null);
    }

    private function replaceResult(ProgressionRunResult $result, ProgressionRunResultStatus $status, ?ExactDecimal $points, ?string $programId, int $attempts): void
    {
        if ($result->isFinal()) {
            return;
        }
        $this->results[$result->runId.'|'.$result->subscriptionId] = new ProgressionRunResult($result->id, $result->runId, $result->subscriptionId, $status, $points, $programId, $attempts);
    }
}
