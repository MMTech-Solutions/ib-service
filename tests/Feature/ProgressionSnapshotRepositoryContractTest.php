<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProgressionSnapshotRepositoryContractTest extends TestCase
{
    use RefreshDatabase;

    public static function drivers(): array
    {
        return [['memory'], ['postgresql']];
    }

    #[DataProvider('drivers')]
    public function test_snapshot_and_prepared_decision_survive_failure_and_do_not_reopen(string $driver): void
    {
        $now = CarbonImmutable::parse('2026-09-11T01:00:00Z');
        $plan = PlanRecord::factory()->create();
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'entry_threshold' => 0]);
        $subscriptionId = (string) Str::uuid();
        DB::table('subscriptions')->insert([
            'id' => $subscriptionId, 'external_user_id' => (string) Str::uuid(), 'plan_id' => $plan->id,
            'origin' => 'user_application', 'requires_approval' => false, 'status' => 'active',
            'activated_at' => $now->subDay(), 'closed_at' => null, 'replaces_subscription_id' => null,
            'lock_version' => 1, 'created_at' => $now->subDay(), 'updated_at' => $now,
        ]);
        $repository = app(ProgressionRunRepositoryFactory::class)->make($driver);
        $window = ProgressionWindow::of($now->subDay()->startOfDay(), $now->startOfDay());
        $run = $repository->findOrCreateRun($plan->id, $window, $now);
        $result = $repository->findOrCreateResult($run->id, $subscriptionId, $now);
        $snapshot = new ProgressionRunSnapshotData(new ProgressionLadderData([['program_id' => $program->id, 'position' => 1, 'entry_threshold' => '0']]),
            [['subscription_id' => $subscriptionId, 'is_evaluable' => true, 'contribution_ids' => [], 'total_points' => '120']], $now->toISOString());
        $repository->saveSnapshot($run->id, $snapshot);
        $repository->saveSnapshot($run->id, new ProgressionRunSnapshotData(new ProgressionLadderData([]), [], $now->addDay()->toISOString()));
        self::assertEquals($snapshot->toArray(), $repository->snapshot($run->id)->toArray());
        self::assertTrue($repository->hasCapturedWindow($plan->id, $window));
        $repository->prepareDecision($result, ExactDecimal::fromString('120'), $program->id, $now);
        $repository->markFailed($result, 'retryable_failure', $now);
        $failed = $repository->failedResults($run->id)[0]->result;
        self::assertSame('120', $failed->totalPoints->value());
        self::assertSame($result->id, $failed->id);
        self::assertSame(1, $failed->attemptCount);
        $repository->prepareDecision($failed, ExactDecimal::fromString('999'), $program->id, $now->addHour());
        self::assertSame('120', $repository->failedResults($run->id)[0]->result->totalPoints->value());
        $repository->markCompleted($failed, ExactDecimal::fromString('999'), $failed->targetProgramId, $now);
        self::assertSame('120', $repository->finalizedResultsAwaitingPlacement($run->id)[0]->totalPoints->value());
        $completed = $repository->finishRun($run, $now);
        self::assertTrue($completed->isCompleted());
        self::assertSame([], $repository->failedResults($run->id));
        $repository->markFailed($result, 'retryable_failure', $now->addDay());
        self::assertSame([], $repository->failedResults($run->id));
        self::assertSame($completed->completedAt->toISOString(), $repository->finishRun($run, $now->addDay())->completedAt->toISOString());
        self::assertSame([], $repository->incompleteRuns($run->id));
    }

    #[DataProvider('drivers')]
    public function test_snapshot_preparation_rolls_back_atomically(string $driver): void
    {
        $now = CarbonImmutable::parse('2026-09-11T01:00:00Z');
        $plan = PlanRecord::factory()->create();
        $repository = app(ProgressionRunRepositoryFactory::class)->make($driver);
        $run = $repository->findOrCreateRun($plan->id, ProgressionWindow::of($now->subDay(), $now), $now);
        try {
            $repository->consistentRead(function () use ($repository, $run, $now): void {
                $repository->saveSnapshot($run->id, new ProgressionRunSnapshotData(new ProgressionLadderData([]), [], $now->toISOString()));
                throw new \RuntimeException('Rollback.');
            });
            self::fail('Failure must propagate.');
        } catch (\RuntimeException) {
            self::assertNull($repository->snapshot($run->id));
        }
    }
}
