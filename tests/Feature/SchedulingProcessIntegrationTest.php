<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Scheduling\DTOs\ManualRunData;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\Factories\SchedulingRunnerProcessFactory;
use App\Features\Scheduling\Services\AdmitSchedulingRunService;
use App\Features\Scheduling\Services\SuperviseSchedulingRunService;
use App\Features\Scheduling\UseCases\SyncSchedulingTasksUseCase;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class SchedulingProcessIntegrationTest extends TestCase
{
    use DatabaseTruncation;

    public function test_real_worker_supervisor_and_runner_execute_and_capture_the_plans_command(): void
    {
        app(SyncSchedulingTasksUseCase::class)->execute();
        $run = app(AdmitSchedulingRunService::class)->manual(new ManualRunData('plans.reconcile-without-operational-modules', 'actor', 'Real process integration', 'real-process'));
        $worker = new Process([PHP_BINARY, base_path('artisan'), 'queue:work', 'scheduling', '--queue=scheduling', '--once', '--tries=1', '--timeout=3660', '--no-interaction'], base_path(), ['DB_CONNECTION' => 'pgsql_testing', 'DB_URL' => '', 'APP_ENV' => 'testing', 'IB_DOMAIN_CLOCK' => 'system', 'RBAC_KAFKA_ENABLED' => 'false'], timeout: 30);
        $worker->run();
        self::assertSame(0, $worker->getExitCode(), 'The isolated scheduling worker failed.');
        $result = app(SchedulingRepositoryFactory::class)->make()->run($run->id);
        self::assertSame('succeeded', $result->status, 'Expected the isolated runner to complete the Plans command.');
        self::assertSame(0, $result->exit_code);
        self::assertStringContainsString('Deactivated plans: 0', $result->stdout);
        self::assertNotNull($result->started_at);
        self::assertNotNull($result->finished_at);
        self::assertSame(0, DB::table('jobs')->where('queue', 'scheduling')->count());
        self::assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_real_process_timeout_is_interrupted_and_never_requeued(): void
    {
        app(SyncSchedulingTasksUseCase::class)->execute();
        $run = app(AdmitSchedulingRunService::class)->manual(new ManualRunData('plans.reconcile-without-operational-modules', 'actor', 'Timeout integration', 'timeout-process'));
        $factory = $this->createMock(SchedulingRunnerProcessFactory::class);
        $factory->method('make')->willReturn(new Process([PHP_BINARY, '-r', 'usleep(2000000);'], timeout: 0.1));
        $this->app->instance(SchedulingRunnerProcessFactory::class, $factory);
        self::assertSame(1, app(SuperviseSchedulingRunService::class)->execute($run->id));
        $result = app(SchedulingRepositoryFactory::class)->make()->run($run->id);
        self::assertSame('interrupted', $result->status);
        self::assertSame('timeout', $result->outcome);
        self::assertSame(1, DB::table('scheduling_runs')->count());
    }

    public function test_native_process_stderr_is_captured_and_redacted(): void
    {
        app(SyncSchedulingTasksUseCase::class)->execute();
        $run = app(AdmitSchedulingRunService::class)->manual(new ManualRunData('plans.reconcile-without-operational-modules', 'actor', 'Native stderr', 'native-output'));
        config()->set('services.example.token', 'native-private-secret');
        $factory = $this->createMock(SchedulingRunnerProcessFactory::class);
        $factory->method('make')->willReturn(new Process([PHP_BINARY, '-r', 'fwrite(STDERR, "token=native-private-secret");'], timeout: 5));
        $this->app->instance(SchedulingRunnerProcessFactory::class, $factory);
        app(SuperviseSchedulingRunService::class)->execute($run->id);
        $result = app(SchedulingRepositoryFactory::class)->make()->run($run->id);
        self::assertSame('interrupted', $result->status);
        self::assertStringContainsString('[REDACTED]', $result->stderr);
        self::assertStringNotContainsString('native-private-secret', $result->stderr);
    }
}
