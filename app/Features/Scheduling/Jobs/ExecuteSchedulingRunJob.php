<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Jobs;

use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Factories\SchedulingRunnerProcessFactory;
use App\Features\Scheduling\Services\SchedulingRunLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ExecuteSchedulingRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly string $runId)
    {
        $this->timeout = (int) config('scheduling.timeout_seconds', 3600) + 60;
    }

    public function handle(SchedulingRepositoryFactory $repositories, SchedulingRunnerProcessFactory $processes, SchedulingRunLifecycle $lifecycle): void
    {
        $run = $repositories->make()->run($this->runId);
        if ($run === null || $run->status !== 'queued') {
            return;
        }
        $process = $processes->make($this->runId, $run->timeout_seconds + 30, 'scheduling:supervise');
        $process->disableOutput();
        try {
            $process->run();
            $lifecycle->interrupt($this->runId, 'supervisor_exited_without_result');
        } catch (Throwable $error) {
            $process->stop(1);
            $lifecycle->interrupt($this->runId, 'worker_exception');
            throw $error;
        }
    }

    public function failed(?Throwable $error): void
    {
        app(SchedulingRunLifecycle::class)->interrupt($this->runId, 'worker_failed');
    }
}
