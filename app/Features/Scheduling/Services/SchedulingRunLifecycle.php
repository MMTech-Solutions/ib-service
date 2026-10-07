<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use Carbon\CarbonImmutable;

final class SchedulingRunLifecycle
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingExecutionMutex $mutex) {}

    public function heartbeat(string $id): void
    {
        $repository = $this->repositories->make();
        $repository->transaction(function () use ($repository, $id): void {
            $run = $repository->run($id, true);
            if ($run !== null && $run->status === 'running') {
                $run->heartbeat_at = CarbonImmutable::now('UTC')->toISOString();
                $repository->saveRun($run);
            }
        });
    }

    public function appendOutput(string $id, RedactedOutputBuffer $stdout, RedactedOutputBuffer $stderr): void
    {
        $repository = $this->repositories->make();
        $repository->transaction(function () use ($repository, $id, $stdout, $stderr): void {
            $run = $repository->run($id, true);
            if ($run === null) {
                return;
            }
            $limit = (int) config('scheduling.output_limit_bytes', 1048576);
            $combinedOut = new RedactedOutputBuffer($limit);
            $combinedErr = new RedactedOutputBuffer($limit);
            $combinedOut->append($run->stdout.$stdout->redacted());
            $combinedErr->append($run->stderr.$stderr->redacted());
            $run->stdout = $combinedOut->redacted();
            $run->stderr = $combinedErr->redacted();
            $run->stdout_truncated = $run->stdout_truncated || $stdout->truncated || $combinedOut->truncated;
            $run->stderr_truncated = $run->stderr_truncated || $stderr->truncated || $combinedErr->truncated;
            $repository->saveRun($run);
        });
    }

    public function interrupt(string $id, string $outcome): bool
    {
        $repository = $this->repositories->make();
        $initial = $repository->run($id);
        if ($initial === null || ! $this->mutex->acquire($initial->task_code)) {
            return false;
        }
        try {
            return $repository->transaction(function () use ($repository, $id, $outcome): bool {
                $run = $repository->run($id, true);
                if ($run === null || ! in_array($run->status, ['queued', 'running'], true)) {
                    return false;
                }
                $run->status = 'interrupted';
                $run->outcome = $outcome;
                $run->finished_at = CarbonImmutable::now('UTC')->toISOString();
                $repository->saveRun($run);

                return true;
            });
        } finally {
            $this->mutex->release($initial->task_code);
        }
    }
}
