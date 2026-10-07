<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Repositories\InMemory;

use App\Features\Scheduling\DTOs\AuditData;
use App\Features\Scheduling\DTOs\PageData;
use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\DTOs\TaskData;
use App\Features\Scheduling\Repositories\SchedulingRepositoryInterface;
use Closure;
use Throwable;

final class InMemorySchedulingRepository implements SchedulingRepositoryInterface
{
    /** @var array<string, TaskData> */
    private array $tasks = [];

    /** @var array<string, RunData> */
    private array $runs = [];

    /** @var list<AuditData> */
    private array $audits = [];

    public function transaction(Closure $operation): mixed
    {
        $backup = serialize([$this->tasks, $this->runs, $this->audits]);
        try {
            return $operation();
        } catch (Throwable $error) {
            [$this->tasks, $this->runs, $this->audits] = unserialize($backup);
            throw $error;
        }
    }

    public function reserveIdempotency(string $key): void {}

    /** @return list<TaskData> */
    public function tasks(): array
    {
        $items = $this->tasks;
        ksort($items);

        return array_map(fn (TaskData $task): TaskData => clone $task, array_values($items));
    }

    public function task(string $code, bool $lock = false): ?TaskData
    {
        return isset($this->tasks[$code]) ? clone $this->tasks[$code] : null;
    }

    public function insertTask(TaskData $task): void
    {
        $this->tasks[$task->code] ??= clone $task;
    }

    public function saveTask(TaskData $task): void
    {
        $this->tasks[$task->code] = clone $task;
    }

    public function appendAudit(AuditData $audit): void
    {
        $this->audits[] = unserialize(serialize($audit));
    }

    public function run(string $id, bool $lock = false): ?RunData
    {
        return isset($this->runs[$id]) ? unserialize(serialize($this->runs[$id])) : null;
    }

    public function keyedRun(string $key): ?RunData
    {
        foreach ($this->runs as $run) {
            if ($run->idempotency_key === $key) {
                return $this->run($run->id);
            }
        }

        return null;
    }

    public function automaticRun(string $code, string $scheduledAt): ?RunData
    {
        foreach ($this->runs as $run) {
            if ($run->task_code === $code && $run->origin === 'automatic' && $run->scheduled_at === $scheduledAt) {
                return $this->run($run->id);
            }
        }

        return null;
    }

    public function activeRun(string $code): ?RunData
    {
        foreach ($this->runs as $run) {
            if ($run->task_code === $code && in_array($run->status, ['queued', 'running'], true)) {
                return $this->run($run->id);
            }
        }

        return null;
    }

    public function insertRun(RunData $run): void
    {
        if (isset($this->runs[$run->id])) {
            throw new \LogicException('Duplicate scheduling run identity.');
        }
        if (($run->idempotency_key !== null && $this->keyedRun($run->idempotency_key) !== null) || ($run->origin === 'automatic' && $run->scheduled_at !== null && $this->automaticRun($run->task_code, $run->scheduled_at) !== null) || (in_array($run->status, ['queued', 'running'], true) && $this->activeRun($run->task_code) !== null)) {
            throw new \LogicException('Duplicate scheduling admission.');
        }
        $this->saveRun($run);
    }

    public function saveRun(RunData $run): void
    {
        foreach ($this->runs as $other) {
            if ($other->id === $run->id) {
                continue;
            }
            if (($run->idempotency_key !== null && $run->idempotency_key === $other->idempotency_key)
                || ($run->origin === 'automatic' && $other->origin === 'automatic' && $run->task_code === $other->task_code && $run->scheduled_at === $other->scheduled_at)
                || ($run->task_code === $other->task_code && in_array($run->status, ['queued', 'running'], true) && in_array($other->status, ['queued', 'running'], true))) {
                throw new \LogicException('Duplicate scheduling admission.');
            }
        }
        $this->runs[$run->id] = unserialize(serialize($run));
    }

    public function page(ReadQueryData $query): PageData
    {
        $items = match ($query->resource) {
            'tasks' => $this->tasks(), 'audits' => $this->audits, default => array_values($this->runs)
        };
        $items = array_values(array_filter($items, static function (TaskData|RunData|AuditData $item) use ($query): bool {
            if ($query->code !== null && ($item instanceof TaskData ? $item->code : $item->task_code) !== $query->code) {
                return false;
            }
            if ($item instanceof RunData) {
                return ($query->status === null || $item->status === $query->status) && ($query->origin === null || $item->origin === $query->origin) && ($query->from === null || $item->accepted_at >= $query->from) && ($query->until === null || $item->accepted_at < $query->until);
            }

            return true;
        }));
        usort($items, static fn (TaskData|RunData|AuditData $a, TaskData|RunData|AuditData $b): int => $a instanceof TaskData ? strcmp($a->code, $b->code) : strcmp(($b instanceof RunData ? $b->accepted_at : $b->occurred_at).$b->id, ($a instanceof RunData ? $a->accepted_at : $a->occurred_at).$a->id));

        return new PageData(unserialize(serialize(array_slice($items, ($query->page - 1) * $query->per_page, $query->per_page))), count($items));
    }

    /** @return list<RunData> */
    public function staleRuns(string $before): array
    {
        return array_values(array_filter(unserialize(serialize($this->runs)), static fn (RunData $run): bool => in_array($run->status, ['queued', 'running'], true) && ($run->heartbeat_at ?? $run->accepted_at) < $before));
    }
}
