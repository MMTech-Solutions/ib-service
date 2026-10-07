<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Repositories;

use App\Features\Scheduling\DTOs\AuditData;
use App\Features\Scheduling\DTOs\PageData;
use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\DTOs\TaskData;
use Closure;

interface SchedulingRepositoryInterface
{
    public function transaction(Closure $operation): mixed;

    public function reserveIdempotency(string $key): void;

    /** @return list<TaskData> */
    public function tasks(): array;

    public function task(string $code, bool $lock = false): ?TaskData;

    public function insertTask(TaskData $task): void;

    public function saveTask(TaskData $task): void;

    public function appendAudit(AuditData $audit): void;

    public function run(string $id, bool $lock = false): ?RunData;

    public function keyedRun(string $key): ?RunData;

    public function automaticRun(string $code, string $scheduledAt): ?RunData;

    public function activeRun(string $code): ?RunData;

    public function insertRun(RunData $run): void;

    public function saveRun(RunData $run): void;

    public function page(ReadQueryData $query): PageData;

    /** @return list<RunData> */
    public function staleRuns(string $before): array;
}
