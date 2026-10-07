<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\DTOs\TaskData;
use Carbon\CarbonImmutable;
use Cron\CronExpression;

final class PresentSchedulingData
{
    public function __construct(private readonly SchedulingTaskRegistry $registry) {}

    /** @return array<string, mixed> */
    public function task(TaskData $task, ?RunData $latest): array
    {
        $definition = $this->registry->find($task->code);
        $available = $this->registry->available($task->code);

        return [...$task->toArray(), 'timezone' => 'UTC', 'timeout_seconds' => $definition->timeout_seconds,
            'effective_enabled' => $task->automatic_enabled && $available,
            'manual_available' => $available,
            'next_due_at' => $task->automatic_enabled && $available ? CarbonImmutable::instance((new CronExpression($task->cron_expression))->getNextRunDate(CarbonImmutable::now('UTC'), timeZone: 'UTC'))->toISOString() : null,
            'latest_run' => $latest ? $this->run($latest) : null];
    }

    /** @return array<string, mixed> */
    public function run(RunData $run): array
    {
        $values = $run->toArray();
        unset($values['stdout'], $values['stderr'], $values['idempotency_key'], $values['request_hash']);
        $values['duration_ms'] = $run->started_at && $run->finished_at ? (int) CarbonImmutable::parse($run->started_at)->diffInMilliseconds(CarbonImmutable::parse($run->finished_at)) : null;

        return $values;
    }

    /** @return array<string, mixed> */
    public function output(RunData $run): array
    {
        return ['id' => $run->id, 'stdout' => $run->stdout, 'stderr' => $run->stderr, 'stdout_truncated' => $run->stdout_truncated, 'stderr_truncated' => $run->stderr_truncated];
    }
}
