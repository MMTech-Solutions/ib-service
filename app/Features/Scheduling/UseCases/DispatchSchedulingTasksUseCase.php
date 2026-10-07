<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Services\AdmitSchedulingRunService;
use App\Features\Scheduling\Services\SchedulingTaskRegistry;
use Carbon\CarbonImmutable;

final class DispatchSchedulingTasksUseCase
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingTaskRegistry $registry, private readonly AdmitSchedulingRunService $admission) {}

    public function execute(): int
    {
        $minute = CarbonImmutable::now('UTC')->startOfMinute();
        $registered = array_column($this->registry->all(), 'code');
        $count = 0;
        foreach ($this->repositories->make()->tasks() as $task) {
            if (in_array($task->code, $registered, true) && $this->admission->automatic($task->code, $minute) !== null) {
                $count++;
            }
        }

        return $count;
    }
}
