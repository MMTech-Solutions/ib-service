<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Console;

use App\Features\Scheduling\Services\SuperviseSchedulingRunService;
use Illuminate\Console\Command;

final class SuperviseSchedulingRunCommand extends Command
{
    protected $signature = 'scheduling:supervise {run : Scheduling run UUID}';

    protected $description = 'Supervise the timeout and heartbeat of an isolated Scheduling runner';

    public function handle(SuperviseSchedulingRunService $supervisor): int
    {
        return $supervisor->execute((string) $this->argument('run'));
    }
}
