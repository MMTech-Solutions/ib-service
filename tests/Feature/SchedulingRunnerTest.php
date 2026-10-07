<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Scheduling\DTOs\ManualRunData;
use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Factories\SchedulingRunnerProcessFactory;
use App\Features\Scheduling\Jobs\ExecuteSchedulingRunJob;
use App\Features\Scheduling\Services\AdmitSchedulingRunService;
use App\Features\Scheduling\Services\ExecuteRegisteredTaskService;
use App\Features\Scheduling\Services\RedactedOutputBuffer;
use App\Features\Scheduling\Services\SchedulingConsoleOutput;
use App\Features\Scheduling\Services\SchedulingExecutionMutex;
use App\Features\Scheduling\Services\SchedulingRunLifecycle;
use App\Features\Scheduling\Services\SuperviseSchedulingRunService;
use App\Features\Scheduling\UseCases\DispatchSchedulingTasksUseCase;
use App\Features\Scheduling\UseCases\ExecuteSchedulingRunUseCase;
use App\Features\Scheduling\UseCases\ReconcileSchedulingRunsUseCase;
use App\Features\Scheduling\UseCases\SyncSchedulingTasksUseCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class SchedulingRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncSchedulingTasksUseCase::class)->execute();
    }

    private function admit(string $code = 'progression:close-windows'): RunData
    {
        return app(AdmitSchedulingRunService::class)->manual(new ManualRunData($code, 'actor', 'Investigate', uniqid('run-', true)));
    }

    private function executor(\Closure $execute): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->method('call')->willReturnCallback($execute);
        $this->app->instance(ExecuteRegisteredTaskService::class, new ExecuteRegisteredTaskService($kernel));
    }

    public function test_disabled_after_acceptance_still_executes_and_locked_domain_is_skipped(): void
    {
        $run = $this->admit();
        DB::table('scheduling_tasks')->where('code', $run->task_code)->update(['automatic_enabled' => false]);
        $this->executor(static function ($command, $arguments, SchedulingConsoleOutput $output): int {
            self::assertSame('progression:close-windows', $command);
            self::assertTrue($arguments['--json']);
            $output->writeln('{"status":"locked"}');

            return 0;
        });
        self::assertSame(0, app(ExecuteSchedulingRunUseCase::class)->execute($run->id));
        $result = app(SchedulingRepositoryFactory::class)->make()->run($run->id);
        self::assertSame('skipped', $result->status);
        self::assertSame('domain_locked', $result->outcome);
        self::assertNotNull($result->finished_at);
        self::assertSame(0, app(ExecuteSchedulingRunUseCase::class)->execute($run->id));
    }

    public function test_success_failure_exception_and_secret_redaction(): void
    {
        config()->set('services.example.token', 'very-private-token');
        $run = $this->admit('rewards:verify-cpa');
        $this->executor(static function ($command, $arguments, SchedulingConsoleOutput $output): int {
            $output->writeln('Completed. token=very-private-token email=user@example.com');
            $output->getErrorOutput()->writeln('Authorization: Bearer another-secret');

            return 0;
        });
        self::assertSame(0, app(ExecuteSchedulingRunUseCase::class)->execute($run->id));
        $result = app(SchedulingRepositoryFactory::class)->make()->run($run->id);
        self::assertSame('succeeded', $result->status);
        self::assertStringNotContainsString('very-private-token', $result->stdout);
        self::assertStringNotContainsString('user@example.com', $result->stdout);
        self::assertStringNotContainsString('another-secret', $result->stderr);
        $failed = $this->admit('rewards:verify-cpa');
        $this->executor(static fn (): int => 3);
        self::assertSame(1, app(ExecuteSchedulingRunUseCase::class)->execute($failed->id));
        self::assertSame(3, app(SchedulingRepositoryFactory::class)->make()->run($failed->id)->exit_code);
        $exception = $this->admit('rewards:verify-cpa');
        $this->executor(static function (): int {
            throw new \RuntimeException('password=never-persist-this');
        });
        app(ExecuteSchedulingRunUseCase::class)->execute($exception->id);
        $result = app(SchedulingRepositoryFactory::class)->make()->run($exception->id);
        self::assertSame('failed', $result->status);
        self::assertStringNotContainsString('never-persist-this', $result->stderr);
        self::assertSame(3, DB::table('jobs')->count());
    }

    public function test_output_limit_handles_split_secrets_and_utf8(): void
    {
        $buffer = new RedactedOutputBuffer(80, ['split-secret']);
        $buffer->append('token=split-');
        $buffer->append("secret\n");
        $buffer->append(str_repeat('ñ', 80));
        $text = $buffer->redacted();
        self::assertTrue($buffer->truncated);
        self::assertLessThanOrEqual(80, strlen($text));
        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        self::assertStringNotContainsString('split-secret', $text);
        self::assertStringContainsString('[REDACTED]', $text);
    }

    public function test_stale_heartbeat_does_not_interrupt_locked_runner(): void
    {
        $run = $this->admit();
        $repository = app(SchedulingRepositoryFactory::class)->make();
        $run->status = 'running';
        $run->started_at = $run->heartbeat_at = now('UTC')->subMinutes(10)->toISOString();
        $repository->saveRun($run);
        config()->set('database.connections.scheduling_blocker', config('database.connections.'.config('database.default')));
        $blocker = new SchedulingExecutionMutex(DB::connection('scheduling_blocker'));
        self::assertTrue($blocker->acquire($run->task_code));
        try {
            self::assertSame(0, app(ReconcileSchedulingRunsUseCase::class)->execute());
            self::assertSame('running', $repository->run($run->id)->status);
        } finally {
            $blocker->release($run->task_code);
            DB::purge('scheduling_blocker');
        }
        self::assertSame(1, app(ReconcileSchedulingRunsUseCase::class)->execute());
        self::assertSame('interrupted', $repository->run($run->id)->status);
        self::assertSame(0, app(ExecuteSchedulingRunUseCase::class)->execute($run->id));
    }

    public function test_supervisor_timeout_and_abrupt_exit_are_terminal_without_retry(): void
    {
        foreach (['timeout', 'runner_exited_without_result'] as $outcome) {
            $run = $this->admit();
            $process = $this->createMock(Process::class);
            $process->method('disableOutput')->willReturnSelf();
            if ($outcome === 'timeout') {
                $process->method('getTimeout')->willReturn(1.0);
                $process->method('getCommandLine')->willReturn('registered-runner');
                $process->method('isRunning')->willReturn(true);
                $process->method('checkTimeout')->willThrowException(new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL));
                $process->expects(self::once())->method('stop');
            } else {
                $process->method('isRunning')->willReturn(false);
            }
            $factory = $this->createMock(SchedulingRunnerProcessFactory::class);
            $factory->expects(self::once())->method('make')->with($run->id, 3600)->willReturn($process);
            $this->app->instance(SchedulingRunnerProcessFactory::class, $factory);
            app(SuperviseSchedulingRunService::class)->execute($run->id);
            $result = app(SchedulingRepositoryFactory::class)->make()->run($run->id);
            self::assertSame('interrupted', $result->status);
            self::assertSame($outcome, $result->outcome);
            $job = new ExecuteSchedulingRunJob($run->id);
            self::assertSame(1, $job->tries);
            $job->handle(app(SchedulingRepositoryFactory::class), $factory, app(SchedulingRunLifecycle::class));
        }
    }

    public function test_worker_failure_cannot_interrupt_a_live_isolated_runner(): void
    {
        $run = $this->admit();
        config()->set('database.connections.scheduling_worker_test', config('database.connections.'.config('database.default')));
        $blocker = new SchedulingExecutionMutex(DB::connection('scheduling_worker_test'));
        self::assertTrue($blocker->acquire($run->task_code));
        try {
            (new ExecuteSchedulingRunJob($run->id))->failed(new \RuntimeException('worker died'));
            self::assertSame('queued', app(SchedulingRepositoryFactory::class)->make()->run($run->id)->status);
        } finally {
            $blocker->release($run->task_code);
            DB::purge('scheduling_worker_test');
        }
    }

    public function test_only_dispatcher_and_recovery_are_registered_and_clock_is_operational(): void
    {
        $events = app(Schedule::class)->events();
        self::assertCount(2, $events);
        self::assertStringContainsString('scheduling:dispatch', $events[0]->command);
        self::assertStringContainsString('scheduling:reconcile', $events[1]->command);
        config()->set('lab.clock_mode', 'controlled');
        $this->travelTo(now('UTC')->setTime(12, 0));
        app(DispatchSchedulingTasksUseCase::class)->execute();
        self::assertSame(now('UTC')->startOfMinute()->toISOString(), app(SchedulingRepositoryFactory::class)->make()->page(new ReadQueryData('runs'))->items[0]->scheduled_at);
    }
}
