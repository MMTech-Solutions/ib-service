<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\DTOs\AuditData;
use App\Features\Scheduling\DTOs\TaskData;
use App\Features\Scheduling\DTOs\UpdateTaskData;
use App\Features\Scheduling\Exceptions\SchedulingException;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Services\SchedulingTaskRegistry;
use App\Features\Scheduling\ValueObjects\CronExpression;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class UpdateSchedulingTaskUseCase
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingTaskRegistry $registry) {}

    public function execute(UpdateTaskData $input): TaskData
    {
        $this->registry->find($input->code);
        if ($input->cron_expression !== null) {
            CronExpression::assertValid($input->cron_expression);
        }
        $repository = $this->repositories->make();

        return $repository->transaction(function () use ($repository, $input): TaskData {
            $task = $repository->task($input->code, true) ?? throw SchedulingException::missing();
            if ($task->version !== $input->version) {
                throw SchedulingException::conflict('Task configuration version has changed.');
            }
            $before = clone $task;
            $task->description = $input->description ?? $task->description;
            $task->cron_expression = $input->cron_expression ?? $task->cron_expression;
            $task->automatic_enabled = $input->automatic_enabled ?? $task->automatic_enabled;
            if ($before->toArray() === $task->toArray()) {
                return $task;
            }
            $task->version++;
            $task->updated_at = CarbonImmutable::now('UTC')->toISOString();
            $repository->saveTask($task);
            $repository->appendAudit(new AuditData((string) Str::uuid7(), $task->code, $input->actor_id, $input->reason, $task->updated_at, $before, clone $task));

            return $task;
        });
    }
}
