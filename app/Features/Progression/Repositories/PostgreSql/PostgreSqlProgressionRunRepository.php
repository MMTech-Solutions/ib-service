<?php

declare(strict_types=1);

namespace App\Features\Progression\Repositories\PostgreSql;

use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Enums\ProgressionRunResultStatus;
use App\Features\Progression\Enums\ProgressionRunStatus;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\Models\ProgressionRunRetry;
use App\Features\Progression\Repositories\PostgreSql\Models\ProgressionRunRecord;
use App\Features\Progression\Repositories\PostgreSql\Models\ProgressionRunResultRecord;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

final class PostgreSqlProgressionRunRepository implements ProgressionRunRepositoryInterface
{
    public function hasCapturedWindow(string $planId, ProgressionWindow $window): bool
    {
        return $this->connection->table('progression_runs')->where('plan_id', $planId)->where('window_starts_at', $window->startsAt)->where('window_ends_at', $window->endsAt)
            ->where(fn ($query) => $query->whereNotNull('snapshot')->orWhereIn('status', ['completed', 'completed_with_errors']))->exists();
    }

    public function incompleteRuns(?string $runId = null): array
    {
        return ProgressionRunRecord::query()->where('status', '!=', 'completed')->when($runId !== null, fn ($query) => $query->whereKey($runId))->orderBy('created_at')->orderBy('id')->get()->map(fn ($record): ProgressionRun => $this->run($record))->all();
    }

    public function snapshot(string $runId): ?ProgressionRunSnapshotData
    {
        $json = $this->connection->table('progression_runs')->where('id', $runId)->value('snapshot');
        if ($json === null) {
            return null;
        }
        $data = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);

        return new ProgressionRunSnapshotData(new ProgressionLadderData($data['ladder']['programs']), $data['participants'], $data['captured_at'], $data['legacy']);
    }

    public function saveSnapshot(string $runId, ProgressionRunSnapshotData $snapshot): void
    {
        $this->connection->table('progression_runs')->where('id', $runId)->whereNull('snapshot')->update(['snapshot' => json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR)]);
    }

    public function contributionIds(string $planId, string $subscriptionId, ProgressionWindow $window): array
    {
        return $this->connection->table('progression_contributions as c')->join('progression_activity_evaluations as e', 'e.id', '=', 'c.evaluation_id')
            ->where('e.status', 'accepted')->where('e.plan_id', $planId)->where('e.subscription_id', $subscriptionId)
            ->where('e.window_starts_at', $window->startsAt)->where('e.window_ends_at', $window->endsAt)
            ->where('e.occurred_at', '>=', $window->startsAt)->where('e.occurred_at', '<', $window->endsAt)
            ->orderBy('c.id')->pluck('c.id')->all();
    }

    public function consistentRead(Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($callback): mixed {
            if ($this->connection->getDriverName() === 'pgsql' && $this->connection->transactionLevel() === 1) {
                $this->connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $callback();
        });
    }

    public function prepareDecision(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void
    {
        $this->connection->table('progression_run_results')->where('id', $result->id)->where('status', 'failed')->whereNull('decision_at')->update([
            'total_points' => $points->value(), 'target_program_id' => $targetProgramId, 'decision_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function recordPlacementFailure(string $resultId, CarbonImmutable $now): void
    {
        $this->connection->table('progression_run_results')->where('id', $resultId)->update([
            'placement_attempt_count' => $this->connection->raw('placement_attempt_count + 1'),
            'placement_failure_code' => 'retryable_failure', 'placement_last_attempt_at' => $now,
        ]);
    }

    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function latestWindowEndsAt(string $planId): ?CarbonImmutable
    {
        $value = ProgressionRunRecord::query()->where('plan_id', $planId)->max('window_ends_at');

        return $value === null ? null : CarbonImmutable::parse((string) $value)->utc();
    }

    public function findOrCreateRun(string $planId, ProgressionWindow $window, CarbonImmutable $now): ProgressionRun
    {
        try {
            $record = $this->connection->transaction(fn () => ProgressionRunRecord::query()->create([
                'id' => (string) Str::uuid7(), 'plan_id' => $planId,
                'window_starts_at' => $window->startsAt, 'window_ends_at' => $window->endsAt,
                'status' => ProgressionRunStatus::Pending->value, 'started_at' => $now,
                'completed_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]));
        } catch (UniqueConstraintViolationException) {
            $record = ProgressionRunRecord::query()->where('plan_id', $planId)
                ->where('window_starts_at', $window->startsAt)->where('window_ends_at', $window->endsAt)->firstOrFail();
        }

        return $this->run($record);
    }

    public function findRun(string $runId): ?ProgressionRun
    {
        $record = ProgressionRunRecord::query()->find($runId);

        return $record === null ? null : $this->run($record);
    }

    public function findOrCreateResult(string $runId, string $subscriptionId, CarbonImmutable $now): ProgressionRunResult
    {
        try {
            $record = $this->connection->transaction(fn () => ProgressionRunResultRecord::query()->create([
                'id' => (string) Str::uuid7(), 'run_id' => $runId, 'subscription_id' => $subscriptionId,
                'status' => ProgressionRunResultStatus::Failed->value, 'total_points' => null,
                'target_program_id' => null, 'attempt_count' => 0, 'failure_message' => 'Pending evaluation.',
                'completed_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]));
        } catch (UniqueConstraintViolationException) {
            $record = ProgressionRunResultRecord::query()->where('run_id', $runId)->where('subscription_id', $subscriptionId)->firstOrFail();
        }

        return $this->result($record);
    }

    public function sumAcceptedContributionPoints(string $planId, string $subscriptionId, ProgressionWindow $window): ExactDecimal
    {
        $points = $this->connection->table('progression_contributions as contributions')
            ->join('progression_activity_evaluations as evaluations', 'evaluations.id', '=', 'contributions.evaluation_id')
            ->where('evaluations.status', 'accepted')->where('evaluations.plan_id', $planId)
            ->where('evaluations.subscription_id', $subscriptionId)
            ->where('evaluations.window_starts_at', $window->startsAt)->where('evaluations.window_ends_at', $window->endsAt)
            ->where('evaluations.occurred_at', '>=', $window->startsAt)->where('evaluations.occurred_at', '<', $window->endsAt)
            ->sum('contributions.points');

        return ExactDecimal::fromString((string) ($points === null ? '0' : $points));
    }

    public function markSkipped(ProgressionRunResult $result, CarbonImmutable $now): void
    {
        $this->updateFinal($result, ProgressionRunResultStatus::Skipped, ExactDecimal::fromString('0'), null, null, $now);
    }

    public function markCompleted(ProgressionRunResult $result, ExactDecimal $points, string $targetProgramId, CarbonImmutable $now): void
    {
        $this->updateFinal($result, ProgressionRunResultStatus::Completed, $points, $targetProgramId, null, $now);
    }

    public function markFailed(ProgressionRunResult $result, string $message, CarbonImmutable $now): void
    {
        ProgressionRunResultRecord::query()->whereKey($result->id)->where('status', ProgressionRunResultStatus::Failed->value)->update([
            'attempt_count' => $result->attemptCount + 1, 'failure_message' => mb_substr($message, 0, 512), 'updated_at' => $now,
        ]);
    }

    public function finishRun(ProgressionRun $run, CarbonImmutable $now): ProgressionRun
    {
        if ($run->isCompleted()) {
            return $run;
        }
        $hasFailures = ProgressionRunResultRecord::query()->where('run_id', $run->id)->where('status', 'failed')->exists();
        ProgressionRunRecord::query()->whereKey($run->id)->where('status', '!=', ProgressionRunStatus::Completed->value)->update([
            'status' => $hasFailures ? ProgressionRunStatus::CompletedWithErrors->value : ProgressionRunStatus::Completed->value,
            'completed_at' => $hasFailures ? null : $now, 'updated_at' => $now,
        ]);

        return $this->run(ProgressionRunRecord::query()->findOrFail($run->id));
    }

    public function failedResults(?string $runId = null): array
    {
        return ProgressionRunResultRecord::query()
            ->join('progression_runs', 'progression_runs.id', '=', 'progression_run_results.run_id')
            ->where('progression_run_results.status', ProgressionRunResultStatus::Failed->value)
            ->when($runId !== null, fn ($query) => $query->where('progression_run_results.run_id', $runId))
            ->select('progression_run_results.*')
            ->orderBy('progression_run_results.updated_at')
            ->get()
            ->map(function (ProgressionRunResultRecord $record): ProgressionRunRetry {
                $run = $this->findRun((string) $record->run_id);

                if ($run === null) {
                    throw new \LogicException('Progression run result references a missing run.');
                }

                return new ProgressionRunRetry($run, $this->result($record));
            })
            ->all();
    }

    public function finalizedResultsAwaitingPlacement(?string $runId = null): array
    {
        return ProgressionRunResultRecord::query()
            ->leftJoin('progression_placement_applications as applications', 'applications.run_result_id', '=', 'progression_run_results.id')
            ->whereNull('applications.run_result_id')
            ->where('progression_run_results.status', ProgressionRunResultStatus::Completed->value)
            ->when($runId !== null, fn ($query) => $query->where('progression_run_results.run_id', $runId))
            ->select('progression_run_results.*')
            ->orderBy('progression_run_results.completed_at')
            ->get()
            ->map(fn (ProgressionRunResultRecord $record): ProgressionRunResult => $this->result($record))
            ->all();
    }

    public function lockFinalizedResultAwaitingPlacement(string $resultId): ?ProgressionRunResult
    {
        $record = ProgressionRunResultRecord::query()->whereKey($resultId)->lockForUpdate()->first();
        if ($record === null || $record->status !== ProgressionRunResultStatus::Completed->value || $record->target_program_id === null || $this->connection->table('progression_placement_applications')->where('run_result_id', $resultId)->exists()) {
            return null;
        }

        return $this->result($record);
    }

    public function recordPlacementApplication(string $resultId, ProgressionPlacementOutcome $outcome, CarbonImmutable $now): void
    {
        $this->connection->table('progression_run_results')->where('id', $resultId)->update([
            'placement_attempt_count' => $this->connection->raw('placement_attempt_count + 1'),
            'placement_failure_code' => null, 'placement_last_attempt_at' => $now,
        ]);
        $this->connection->table('progression_placement_applications')->insert([
            'run_result_id' => $resultId,
            'outcome' => $outcome->value,
            'applied_at' => $now,
        ]);
    }

    private function updateFinal(ProgressionRunResult $result, ProgressionRunResultStatus $status, ExactDecimal $points, ?string $targetProgramId, ?string $failureMessage, CarbonImmutable $now): void
    {
        if ($result->isFinal()) {
            return;
        }
        $this->connection->transaction(function () use ($result, $status, $points, $targetProgramId, $failureMessage, $now): void {
            $stored = ProgressionRunResultRecord::query()->whereKey($result->id)->where('status', 'failed')->lockForUpdate()->first();
            if ($stored === null) {
                return;
            }
            if ($stored->decision_at !== null && $status === ProgressionRunResultStatus::Completed) {
                $points = ExactDecimal::fromString((string) $stored->total_points);
                $targetProgramId = (string) $stored->target_program_id;
            }
            $stored->update([
                'status' => $status->value, 'total_points' => $points->value(), 'target_program_id' => $targetProgramId,
                'failure_message' => $failureMessage, 'completed_at' => $now, 'updated_at' => $now,
                'attempt_count' => (int) $stored->attempt_count + 1,
            ]);
        });
    }

    private function run(ProgressionRunRecord $record): ProgressionRun
    {
        return new ProgressionRun((string) $record->id, (string) $record->plan_id, ProgressionWindow::of($record->window_starts_at, $record->window_ends_at), ProgressionRunStatus::from((string) $record->status), $record->started_at, $record->completed_at, (int) $record->snapshot_generation === 0);
    }

    private function result(ProgressionRunResultRecord $record): ProgressionRunResult
    {
        return new ProgressionRunResult((string) $record->id, (string) $record->run_id, (string) $record->subscription_id, ProgressionRunResultStatus::from((string) $record->status), $record->total_points === null ? null : ExactDecimal::fromString((string) $record->total_points), $record->target_program_id === null ? null : (string) $record->target_program_id, (int) $record->attempt_count);
    }
}
