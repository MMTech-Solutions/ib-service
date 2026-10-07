<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Console;

use App\Features\Scheduling\UseCases\SyncSchedulingTasksUseCase;
use Illuminate\Console\Command;

final class SyncSchedulingTasksCommand extends Command
{
    protected $signature = 'scheduling:sync';

    protected $description = 'scheduling:sync for the registered Scheduling catalog';

    public function handle(SyncSchedulingTasksUseCase $useCase): int
    {
        $useCase->execute();
        $this->info('Scheduling catalog synchronized.');

        return 0;
    }
}
