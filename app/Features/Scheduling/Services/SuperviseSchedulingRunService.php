<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Factories\SchedulingRunnerProcessFactory;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class SuperviseSchedulingRunService
{
    public function __construct(private readonly SchedulingRepositoryFactory $repositories, private readonly SchedulingRunnerProcessFactory $processes, private readonly SchedulingRunLifecycle $lifecycle, private readonly SchedulingOutputSecrets $outputSecrets) {}

    public function execute(string $id): int
    {
        $run = $this->repositories->make()->run($id);
        if ($run === null || $run->status !== 'queued') {
            return 0;
        }
        $process = $this->processes->make($id, $run->timeout_seconds);
        $limit = (int) config('scheduling.output_limit_bytes', 1048576);
        $stdout = new RedactedOutputBuffer($limit, $this->outputSecrets->all());
        $stderr = new RedactedOutputBuffer($limit, $this->outputSecrets->all());
        try {
            $process->start(static function (string $type, string $chunk) use ($stdout, $stderr): void {
                ($type === Process::OUT ? $stdout : $stderr)->append($chunk);
            });
            $heartbeatAt = 0.0;
            while ($process->isRunning()) {
                $process->checkTimeout();
                $process->clearOutput();
                $process->clearErrorOutput();
                if (microtime(true) - $heartbeatAt >= (int) config('scheduling.heartbeat_seconds', 15)) {
                    $this->lifecycle->heartbeat($id);
                    $heartbeatAt = microtime(true);
                }
                usleep(200000);
            }
            $this->lifecycle->interrupt($id, 'runner_exited_without_result');

            return 0;
        } catch (ProcessTimedOutException) {
            $process->stop(1);
            $this->lifecycle->interrupt($id, 'timeout');

            return 1;
        } catch (Throwable $error) {
            $process->stop(1);
            $this->lifecycle->interrupt($id, 'runner_exception');
            throw $error;
        } finally {
            $this->lifecycle->appendOutput($id, $stdout, $stderr);
        }
    }
}
