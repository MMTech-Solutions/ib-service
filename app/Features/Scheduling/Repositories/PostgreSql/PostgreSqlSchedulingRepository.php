<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Repositories\PostgreSql;

use App\Features\Scheduling\DTOs\AuditData;
use App\Features\Scheduling\DTOs\PageData;
use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\DTOs\TaskData;
use App\Features\Scheduling\Repositories\SchedulingRepositoryInterface;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final class PostgreSqlSchedulingRepository implements SchedulingRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $operation): mixed
    {
        return $this->connection->transaction($operation);
    }

    public function reserveIdempotency(string $key): void
    {
        $this->connection->selectOne('SELECT pg_advisory_xact_lock(?, hashtext(?))', [734211, $key]);
    }

    /** @return list<TaskData> */
    public function tasks(): array
    {
        return $this->connection->table('scheduling_tasks')->orderBy('code')->get()->map(fn (object $row): TaskData => $this->taskData($row))->all();
    }

    public function task(string $code, bool $lock = false): ?TaskData
    {
        $query = $this->connection->table('scheduling_tasks')->where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();

        return $row ? $this->taskData($row) : null;
    }

    public function insertTask(TaskData $task): void
    {
        $this->connection->table('scheduling_tasks')->insertOrIgnore($task->toArray());
    }

    public function saveTask(TaskData $task): void
    {
        $this->connection->table('scheduling_tasks')->where('code', $task->code)->update($task->toArray());
    }

    public function appendAudit(AuditData $audit): void
    {
        $values = $audit->toArray();
        $values['before'] = json_encode($values['before'], JSON_THROW_ON_ERROR);
        $values['after'] = json_encode($values['after'], JSON_THROW_ON_ERROR);
        $this->connection->table('scheduling_audits')->insert($values);
    }

    public function run(string $id, bool $lock = false): ?RunData
    {
        $query = $this->connection->table('scheduling_runs')->where('id', $id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $this->firstRun($query);
    }

    public function keyedRun(string $key): ?RunData
    {
        return $this->firstRun($this->connection->table('scheduling_runs')->where('idempotency_key', $key));
    }

    public function automaticRun(string $code, string $scheduledAt): ?RunData
    {
        return $this->firstRun($this->connection->table('scheduling_runs')->where('task_code', $code)->where('origin', 'automatic')->where('scheduled_at', $scheduledAt));
    }

    public function activeRun(string $code): ?RunData
    {
        return $this->firstRun($this->connection->table('scheduling_runs')->where('task_code', $code)->whereIn('status', ['queued', 'running']));
    }

    public function insertRun(RunData $run): void
    {
        $this->connection->table('scheduling_runs')->insert($this->runValues($run));
    }

    public function saveRun(RunData $run): void
    {
        $this->connection->table('scheduling_runs')->where('id', $run->id)->update($this->runValues($run));
    }

    public function page(ReadQueryData $query): PageData
    {
        $table = match ($query->resource) {
            'tasks' => 'scheduling_tasks', 'audits' => 'scheduling_audits', default => 'scheduling_runs'
        };
        $builder = $this->connection->table($table);
        if ($query->code !== null) {
            $builder->where($table === 'scheduling_tasks' ? 'code' : 'task_code', $query->code);
        }
        if ($table === 'scheduling_runs') {
            if ($query->status !== null) {
                $builder->where('status', $query->status);
            }
            if ($query->origin !== null) {
                $builder->where('origin', $query->origin);
            }
            if ($query->from !== null) {
                $builder->where('accepted_at', '>=', $query->from);
            }
            if ($query->until !== null) {
                $builder->where('accepted_at', '<', $query->until);
            }
        }
        $total = (clone $builder)->count();
        $builder->orderBy($table === 'scheduling_tasks' ? 'code' : ($table === 'scheduling_audits' ? 'occurred_at' : 'accepted_at'), $table === 'scheduling_tasks' ? 'asc' : 'desc');
        if ($table !== 'scheduling_tasks') {
            $builder->orderBy('id', 'desc');
        }
        $items = $builder->offset(($query->page - 1) * $query->per_page)->limit($query->per_page)->get()->map(fn (object $row): TaskData|RunData|AuditData => match ($table) {
            'scheduling_tasks' => $this->taskData($row),
            'scheduling_audits' => AuditData::from([...(array) $row, 'before' => TaskData::from(json_decode($row->before, true, flags: JSON_THROW_ON_ERROR)), 'after' => TaskData::from(json_decode($row->after, true, flags: JSON_THROW_ON_ERROR)), 'occurred_at' => CarbonImmutable::parse($row->occurred_at)->toISOString()]),
            default => $this->runData($row),
        })->all();

        return new PageData($items, $total);
    }

    /** @return list<RunData> */
    public function staleRuns(string $before): array
    {
        return $this->connection->table('scheduling_runs')->whereIn('status', ['queued', 'running'])->whereRaw('COALESCE(heartbeat_at, accepted_at) < ?', [$before])->get()->map(fn (object $row): RunData => $this->runData($row))->all();
    }

    private function taskData(object $row): TaskData
    {
        return TaskData::from([...(array) $row, 'updated_at' => $row->updated_at ? CarbonImmutable::parse($row->updated_at)->toISOString() : null]);
    }

    private function firstRun(Builder $query): ?RunData
    {
        $row = $query->first();

        return $row ? $this->runData($row) : null;
    }

    private function runData(object $row): RunData
    {
        $values = (array) $row;
        $values['configuration'] = TaskData::from(json_decode($row->configuration, true, flags: JSON_THROW_ON_ERROR));
        foreach (['accepted_at', 'scheduled_at', 'started_at', 'heartbeat_at', 'finished_at'] as $field) {
            $values[$field] = $values[$field] ? CarbonImmutable::parse($values[$field])->toISOString() : null;
        }

        return RunData::from($values);
    }

    /** @return array<string, mixed> */
    private function runValues(RunData $run): array
    {
        $values = $run->toArray();
        $values['configuration'] = json_encode($values['configuration'], JSON_THROW_ON_ERROR);

        return $values;
    }
}
