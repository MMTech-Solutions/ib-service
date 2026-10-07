<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Scheduling\DTOs\ReadQueryData;
use App\Features\Scheduling\DTOs\RunData;
use App\Features\Scheduling\DTOs\TaskData;
use App\Features\Scheduling\DTOs\UpdateTaskData;
use App\Features\Scheduling\Factories\SchedulingRepositoryFactory;
use App\Features\Scheduling\UseCases\SyncSchedulingTasksUseCase;
use App\Features\Scheduling\UseCases\UpdateSchedulingTaskUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SchedulingRepositoryContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function drivers(): array
    {
        return ['memory' => ['memory'], 'postgresql' => ['postgresql']];
    }

    #[DataProvider('drivers')]
    public function test_sync_update_audit_rollback_and_historical_snapshot(string $driver): void
    {
        config()->set('scheduling.repository', $driver);
        $repository = app(SchedulingRepositoryFactory::class)->make();
        app(SyncSchedulingTasksUseCase::class)->execute();
        self::assertCount(7, $repository->tasks());
        $task = $repository->task('progression:close-windows');
        $run = new RunData((string) Str::uuid7(), $task->code, 'manual', clone $task, '2026-10-07T12:00:00.000000Z', idempotency_key: hash('sha256', 'key'), request_hash: hash('sha256', 'payload'));
        $repository->insertRun($run);
        $changed = app(UpdateSchedulingTaskUseCase::class)->execute(new UpdateTaskData($task->code, 1, 'actor', 'Pause', automatic_enabled: false));
        self::assertSame(2, $changed->version);
        self::assertSame(1, $repository->run($run->id)->configuration->version);
        self::assertSame($run->id, $repository->keyedRun(hash('sha256', 'key'))->id);
        self::assertSame($run->id, $repository->activeRun($task->code)->id);
        self::assertSame(1, $repository->page(new ReadQueryData('audits', code: $task->code))->total);
        try {
            $repository->transaction(function () use ($repository, $changed): void {
                $changed->description = 'Must roll back';
                $repository->saveTask($changed);
                throw new \RuntimeException('rollback');
            });
            self::fail('Expected rollback');
        } catch (\RuntimeException) {
            self::assertNotSame('Must roll back', $repository->task($task->code)->description);
        }
        $run->status = 'succeeded';
        $repository->saveRun($run);
        self::assertNull($repository->activeRun($task->code));
        self::assertSame(1, $repository->page(new ReadQueryData('runs', code: $task->code, status: 'succeeded'))->total);
    }

    #[DataProvider('drivers')]
    public function test_duplicate_admissions_are_rejected(string $driver): void
    {
        $repository = app(SchedulingRepositoryFactory::class)->make($driver);
        $task = new TaskData('test', 'Test task', '* * * * *');
        $repository->insertTask($task);
        $first = new RunData((string) Str::uuid7(), 'test', 'automatic', $task, '2026-10-07T12:00:00.000000Z', scheduled_at: '2026-10-07T12:00:00.000000Z');
        $repository->insertRun($first);
        $this->expectException(\Throwable::class);
        $repository->transaction(fn () => $repository->insertRun(new RunData((string) Str::uuid7(), 'test', 'manual', $task, '2026-10-07T12:00:01.000000Z')));
    }

    #[DataProvider('drivers')]
    public function test_automatic_slot_remains_unique_after_completion(string $driver): void
    {
        $repository = app(SchedulingRepositoryFactory::class)->make($driver);
        $task = new TaskData('test', 'Test task', '* * * * *');
        $repository->insertTask($task);
        $run = new RunData((string) Str::uuid7(), 'test', 'automatic', $task, '2026-10-07T12:00:00.000000Z', status: 'succeeded', scheduled_at: '2026-10-07T12:00:00.000000Z', finished_at: '2026-10-07T12:00:01.000000Z');
        $repository->insertRun($run);
        self::assertSame($run->id, $repository->automaticRun('test', $run->scheduled_at)->id);
        $duplicate = clone $run;
        $duplicate->id = (string) Str::uuid7();
        $this->expectException(\Throwable::class);
        $repository->transaction(fn () => $repository->insertRun($duplicate));
    }
}
