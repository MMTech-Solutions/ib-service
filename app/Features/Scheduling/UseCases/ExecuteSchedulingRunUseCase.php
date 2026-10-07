<?php

declare(strict_types=1);

namespace App\Features\Scheduling\UseCases;

use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\Exceptions\SchedulingException;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Services\ExecuteRegisteredTaskService;
use App\Features\Scheduling\Services\RedactedOutputBuffer;
use App\Features\Scheduling\Services\SchedulingConsoleOutput;
use App\Features\Scheduling\Services\SchedulingExecutionMutex;
use App\Features\Scheduling\Services\SchedulingOutputSecrets;
use App\Features\Scheduling\Services\SchedulingTaskRegistry;
use Carbon\CarbonImmutable;
use Throwable;

final class ExecuteSchedulingRunUseCase
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingExecutionMutex $mutex, private readonly SchedulingTaskRegistry $registry, private readonly ExecuteRegisteredTaskService $executor, private readonly SchedulingOutputSecrets $outputSecrets) {}

    public function execute(string $id): int
    {
        $repository = $this->repositories->make();
        $initial = $repository->run($id) ?? throw SchedulingException::missing();
        if ($initial->status !== 'queued') {
            return 0;
        }
        if (! $this->mutex->acquire($initial->task_code)) {
            $repository->transaction(function () use ($repository, $id): void {
                $run = $repository->run($id, true);
                if ($run !== null && $run->status === 'queued') {
                    $run->status = 'skipped';
                    $run->outcome = 'execution_locked';
                    $run->finished_at = CarbonImmutable::now('UTC')->toISOString();
                    $repository->saveRun($run);
                }
            });

            return 0;
        }
        try {
            $run = $repository->transaction(function () use ($repository, $id): ?RunData {
                $run = $repository->run($id, true);
                if ($run === null || $run->status !== 'queued') {
                    return null;
                }
                $run->status = 'running';
                $run->started_at = $run->heartbeat_at = CarbonImmutable::now('UTC')->toISOString();
                $repository->saveRun($run);

                return $run;
            });
            if ($run === null) {
                return 0;
            }
            $secrets = $this->outputSecrets->all();
            $limit = (int) config('scheduling.output_limit_bytes', 1048576);
            $output = new SchedulingConsoleOutput(new RedactedOutputBuffer($limit, $secrets), new RedactedOutputBuffer($limit, $secrets));
            $checkpointAt = 0.0;
            $checkpoint = function () use ($repository, $id, $output, &$checkpointAt): void {
                if (microtime(true) - $checkpointAt < 1) {
                    return;
                }
                $repository->transaction(function () use ($repository, $id, $output): void {
                    $current = $repository->run($id, true);
                    if ($current !== null && $current->status === 'running') {
                        $current->stdout = $output->stdout->redacted(true);
                        $current->stderr = $output->stderr->redacted(true);
                        $current->stdout_truncated = $output->stdout->truncated;
                        $current->stderr_truncated = $output->stderr->truncated;
                        $current->heartbeat_at = CarbonImmutable::now('UTC')->toISOString();
                        $repository->saveRun($current);
                    }
                });
                $checkpointAt = microtime(true);
            };
            $output->stdout->onWrite = $output->stderr->onWrite = $checkpoint;
            ob_start(static function (string $chunk) use ($output): string {
                $output->stdout->append($chunk);

                return '';
            }, 4096);
            try {
                if (! $this->registry->available($run->task_code)) {
                    $run->status = 'skipped';
                    $run->outcome = 'feature_disabled';
                } else {
                    $run->exit_code = $this->executor->execute($this->registry->find($run->task_code), $output);
                    $run->status = $run->exit_code === 0 ? 'succeeded' : 'failed';
                    $run->outcome = $run->exit_code === 0 ? 'command_completed' : 'command_failed';
                    if ($run->task_code === 'progression:close-windows') {
                        $report = json_decode(trim($output->stdout->raw()), true);
                        if (is_array($report) && ($report['status'] ?? null) === 'locked') {
                            $run->status = 'skipped';
                            $run->outcome = 'domain_locked';
                        }
                    }
                }
            } catch (Throwable $error) {
                $run->status = 'failed';
                $run->exit_code = 1;
                $run->outcome = 'command_exception';
                $output->stderr->writeln('Task raised '.$error::class.'. Inspect protected service logs.');
            } finally {
                ob_end_flush();
            }
            $run->stdout = $output->stdout->redacted();
            $run->stderr = $output->stderr->redacted();
            $run->stdout_truncated = $output->stdout->truncated;
            $run->stderr_truncated = $output->stderr->truncated;
            $run->finished_at = $run->heartbeat_at = CarbonImmutable::now('UTC')->toISOString();
            $repository->saveRun($run);

            return $run->status === 'failed' ? 1 : 0;
        } finally {
            $this->mutex->release($initial->task_code);
        }
    }
}
