<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\DTOs\TaskData;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Services\SchedulingTaskRegistry;
use Carbon\CarbonImmutable;

final class SyncSchedulingTasksUseCase
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingTaskRegistry $registry) {}

    public function execute(): void
    {
        $repository = $this->repositories->make();
        $repository->transaction(function () use ($repository): void {
            foreach ($this->registry->all() as $definition) {
                $repository->insertTask(new TaskData($definition->code, $definition->description, $definition->cron_expression, updated_at: CarbonImmutable::now('UTC')->toISOString()));
            }
        });
    }
}
