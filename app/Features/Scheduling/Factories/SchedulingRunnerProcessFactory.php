<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Factories;

use Symfony\Component\Process\Process;

class SchedulingRunnerProcessFactory
{
    public function make(string $id, int $timeout, string $command = 'scheduling:execute'): Process
    {
        return new Process([PHP_BINARY, base_path('artisan'), $command, $id, '--no-interaction'], base_path(), ['DB_CONNECTION' => (string) config('database.default')], timeout: $timeout);
    }
}
