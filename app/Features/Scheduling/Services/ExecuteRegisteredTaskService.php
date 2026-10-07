<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\DTOs\TaskDefinitionData;
use Illuminate\Contracts\Console\Kernel;

final class ExecuteRegisteredTaskService
{
    public function __construct(private readonly Kernel $console) {}

    public function execute(TaskDefinitionData $definition, SchedulingConsoleOutput $output): int
    {
        return $this->console->call($definition->command, [...$definition->arguments, '--no-interaction' => true], $output);
    }
}
