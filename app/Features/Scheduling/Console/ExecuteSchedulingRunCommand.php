<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Console;

use App\Features\Scheduling\UseCases\ExecuteSchedulingRunUseCase;
use Illuminate\Console\Command;

final class ExecuteSchedulingRunCommand extends Command
{
    protected $signature = 'scheduling:execute {run : Scheduling run UUID}';

    protected $description = 'scheduling:execute for the registered Scheduling catalog';

    public function handle(ExecuteSchedulingRunUseCase $useCase): int
    {
        return $useCase->execute((string) $this->argument('run'));
    }
}
