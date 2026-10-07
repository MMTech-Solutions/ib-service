<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Console;

use App\Features\Scheduling\UseCases\DispatchSchedulingTasksUseCase;
use Illuminate\Console\Command;

final class DispatchSchedulingTasksCommand extends Command
{
    protected $signature = 'scheduling:dispatch';

    protected $description = 'scheduling:dispatch for the registered Scheduling catalog';

    public function handle(DispatchSchedulingTasksUseCase $useCase): int
    {
        $this->info('Scheduling admissions: '.$useCase->execute());

        return 0;
    }
}
