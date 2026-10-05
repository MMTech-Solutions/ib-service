<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use App\Features\Programs\Contracts\Ports\Input\CaptureProgressionLadderPort;
use App\Features\Progression\Contracts\Ports\Output\ProgressionFailurePort;
use App\Features\Progression\Contracts\Repositories\ProgressionRunRepositoryInterface;
use App\Features\Progression\DTOs\ProgressionReadQueryData;
use App\Features\Progression\DTOs\ProgressionRunSnapshotData;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Models\ProgressionRun;
use App\Features\Progression\Models\ProgressionRunResult;
use App\Features\Progression\Repositories\PostgreSql\PostgreSqlProgressionReadRepository;
use App\Features\Progression\Services\PrepareProgressionRecoveryService;
use App\Features\Progression\UseCases\RecoverProgressionRunsUseCase;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProgressionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private PlanRecord $plan;

    private ProgramRecord $first;

    private ProgramRecord $second;

    private ProgressionRunRepositoryInterface $repository;

    private ProgressionRun $run;

    private ProgressionRunResult $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-11T01:00:00Z');
        $this->plan = PlanRecord::factory()->create();
        $this->first = ProgramRecord::factory()->create(['plan_id' => $this->plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $this->second = ProgramRecord::factory()->create(['plan_id' => $this->plan->id, 'position' => 2, 'entry_threshold' => 100]);
        config()->set(['progression.repository' => 'postgresql', 'subscriptions.repository' => 'postgresql', 'programs.repository' => 'postgresql']);
    }

    private function prepare(string $driver = 'postgresql', bool $snapshot = true): void
    {
        $subscriptionId = (string) Str::uuid();
        DB::table('subscriptions')->insert([
            'id' => $subscriptionId, 'external_user_id' => (string) Str::uuid(), 'plan_id' => $this->plan->id,
            'origin' => 'user_application', 'requires_approval' => false, 'status' => 'active',
            'activated_at' => $this->now->subDay(), 'lock_version' => 1, 'created_at' => $this->now->subDay(), 'updated_at' => $this->now,
        ]);
        DB::table('subscription_placements')->insert([
            'id' => (string) Str::uuid(), 'subscription_id' => $subscriptionId, 'program_id' => $this->first->id,
            'is_fixed' => false, 'effective_from' => $this->now->subDay(), 'created_at' => $this->now, 'updated_at' => $this->now,
        ]);
        $this->repository = app(ProgressionRunRepositoryFactory::class)->make($driver);
        $this->run = $this->repository->findOrCreateRun($this->plan->id, ProgressionWindow::of($this->now->subDay()->startOfDay(), $this->now->startOfDay()), $this->now);
        $this->result = $this->repository->findOrCreateResult($this->run->id, $subscriptionId, $this->now);
        if ($snapshot) {
            $ladder = new ProgressionLadderData([
                ['program_id' => $this->first->id, 'position' => 1, 'entry_threshold' => '0'],
                ['program_id' => $this->second->id, 'position' => 2, 'entry_threshold' => '100'],
            ]);
            $this->repository->saveSnapshot($this->run->id, new ProgressionRunSnapshotData($ladder, [
                ['subscription_id' => $subscriptionId, 'is_evaluable' => true, 'contribution_ids' => [], 'total_points' => '120.12345678'],
            ], $this->now->toISOString()));
        }
    }

    private function attempts(): array
    {
        return json_decode(DB::table('progression_run_results')->where('id', $this->result->id)->value('recovery_attempts') ?? '[]', true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_recovery_changes_target_preserves_original_and_applies_only_once(): void
    {
        $this->prepare();
        $this->repository->prepareDecision($this->result, ExactDecimal::fromString('120.12345678'), $this->second->id, $this->now);
        $this->second->update(['entry_threshold' => 200]);
        $snapshot = $this->repository->snapshot($this->run->id)->toArray();
        $recover = app(RecoverProgressionRunsUseCase::class);
        $report = $recover->execute($this->run->id, $this->now->addMinute());
        self::assertSame(1, $report->results_recovered);
        self::assertSame(1, $report->placements_unchanged);
        $row = DB::table('progression_run_results')->where('id', $this->result->id)->first();
        self::assertSame($this->first->id, $row->target_program_id);
        self::assertSame('120.12345678', ExactDecimal::fromString($row->total_points)->value());
        self::assertSame($this->second->id, json_decode($row->original_decision, true)['target_program_id']);
        self::assertSame($snapshot, $this->repository->snapshot($this->run->id)->toArray());
        self::assertCount(1, $this->attempts());
        self::assertSame('placement', $this->attempts()[0]['stage']);
        self::assertSame('200', $this->attempts()[0]['ladder']['programs'][1]['entry_threshold']);
        $this->second->update(['entry_threshold' => 50]);
        $again = $recover->execute($this->run->id, $this->now->addMinutes(2));
        self::assertSame(0, $again->results_recovered);
        self::assertSame(0, $again->placements_applied);
        self::assertCount(1, $this->attempts());
        self::assertSame(1, DB::table('progression_placement_applications')->where('run_result_id', $this->result->id)->count());
    }

    public function test_completed_run_recovers_placement_with_each_attempt_preserved(): void
    {
        $this->prepare();
        $this->repository->prepareDecision($this->result, ExactDecimal::fromString('120.12345678'), $this->second->id, $this->now);
        $this->repository->markCompleted($this->result, ExactDecimal::fromString('120.12345678'), $this->second->id, $this->now);
        $this->repository->finishRun($this->run, $this->now);
        $completedAt = DB::table('progression_runs')->where('id', $this->run->id)->value('completed_at');
        $this->second->update(['entry_threshold' => 200]);
        $this->mock(ProgressionFailurePort::class)->shouldReceive('check')->once()->with('recover', 'before_placement', $this->result->subscriptionId, $this->result->id)->andThrow(new \RuntimeException('Private detail'));
        self::assertSame(1, app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now->addMinute())->placements_failed);
        self::assertSame('failed', $this->attempts()[0]['outcome']);
        self::assertSame('retryable_failure', $this->attempts()[0]['failure_code']);
        $this->second->update(['entry_threshold' => 50]);
        $this->mock(ProgressionFailurePort::class)->shouldReceive('check')->once()->andReturnNull();
        self::assertSame(1, app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now->addMinutes(2))->placements_applied);
        self::assertCount(2, $this->attempts());
        self::assertSame('50', $this->attempts()[1]['ladder']['programs'][1]['entry_threshold']);
        self::assertSame('completed', DB::table('progression_runs')->where('id', $this->run->id)->value('status'));
        self::assertSame($completedAt, DB::table('progression_runs')->where('id', $this->run->id)->value('completed_at'));
    }

    public function test_missing_historical_points_remain_failed_without_ledger_queries(): void
    {
        $this->prepare(snapshot: false);
        DB::enableQueryLog();
        $report = app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        self::assertSame(1, $report->results_failed);
        self::assertSame('missing_points_evidence', $this->attempts()[0]['failure_code']);
        self::assertNull($this->repository->snapshot($this->run->id));
        self::assertNull($this->repository->failedResults($this->run->id)[0]->result->totalPoints);
        foreach ($queries as $query) {
            self::assertStringNotContainsString('progression_contributions', $query['query']);
            self::assertStringNotContainsString('progression_activity_evaluations', $query['query']);
        }
    }

    public function test_repeated_finalization_failure_keeps_every_decision_and_reuses_it_for_placement(): void
    {
        $this->prepare();
        $this->repository->prepareDecision($this->result, ExactDecimal::fromString('120.12345678'), $this->second->id, $this->now);
        $this->second->update(['entry_threshold' => 200]);
        $this->mock(ProgressionFailurePort::class)->shouldReceive('check')->once()->with('recover', 'before_result_finalize', $this->result->subscriptionId, $this->result->id)->andThrow(new \RuntimeException('Failure'));
        self::assertSame(1, app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now->addMinute())->results_failed);
        self::assertSame('finalize', $this->attempts()[0]['stage']);
        self::assertSame($this->first->id, $this->attempts()[0]['target_program_id']);
        $this->second->update(['entry_threshold' => 50]);
        $this->mock(ProgressionFailurePort::class)->shouldReceive('check')->twice()->andReturnNull();
        self::assertSame(1, app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now->addMinutes(2))->placements_applied);
        self::assertCount(2, $this->attempts());
        self::assertSame($this->second->id, $this->attempts()[1]['target_program_id']);
        self::assertSame('120.12345678', $this->attempts()[1]['total_points']);
        self::assertSame('placement', $this->attempts()[1]['stage']);
    }

    public function test_old_snapshot_key_is_ignored_and_original_read_decision_is_absent_when_never_prepared(): void
    {
        $this->prepare();
        $snapshot = $this->repository->snapshot($this->run->id)->toArray();
        $snapshot['legacy'] = true;
        DB::table('progression_runs')->where('id', $this->run->id)->update(['snapshot' => json_encode($snapshot)]);
        self::assertArrayNotHasKey('legacy', $this->repository->snapshot($this->run->id)->toArray());
        app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now);
        $read = new PostgreSqlProgressionReadRepository(DB::connection());
        $page = $read->read(new ProgressionReadQueryData('results', $this->result->id, $this->run->id, []));
        self::assertNull($page->items[0]->original_decision);
        self::assertCount(1, $page->items[0]->recovery_attempts);
        self::assertSame('completed', $page->items[0]->placement['status']);
    }

    public function test_migration_preserves_prepared_decision_without_fabricating_missing_history(): void
    {
        $this->prepare();
        $this->repository->prepareDecision($this->result, ExactDecimal::fromString('120.12345678'), $this->second->id, $this->now);
        $migration = require database_path('migrations/2026_10_05_172338_add_progression_recovery_evidence.php');
        $migration->down();
        $migration->up();
        $row = DB::table('progression_run_results')->where('id', $this->result->id)->first();
        $original = json_decode($row->original_decision, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($this->second->id, $original['target_program_id']);
        self::assertSame($this->now->toISOString(), $original['decision_at']);
        self::assertNull($row->recovery_attempts);
        self::assertFalse(Schema::hasColumn('progression_runs', 'snapshot_generation'));
    }

    public static function terminalOutcomes(): array
    {
        return [['fixed'], ['not_active']];
    }

    #[DataProvider('terminalOutcomes')]
    public function test_terminal_placement_outcomes_are_never_recovered_again(string $outcome): void
    {
        $this->prepare();
        if ($outcome === 'fixed') {
            DB::table('subscription_placements')->where('subscription_id', $this->result->subscriptionId)->update(['is_fixed' => true]);
        } else {
            DB::table('subscriptions')->where('id', $this->result->subscriptionId)->update(['status' => 'ended', 'closed_at' => $this->now]);
            DB::table('subscription_placements')->where('subscription_id', $this->result->subscriptionId)->update(['effective_until' => $this->now]);
        }
        $recover = app(RecoverProgressionRunsUseCase::class);
        $recover->execute($this->run->id, $this->now);
        self::assertSame($outcome, $this->attempts()[0]['outcome']);
        self::assertSame($outcome, DB::table('progression_placement_applications')->where('run_result_id', $this->result->id)->value('outcome'));
        $recover->execute($this->run->id, $this->now->addMinute());
        self::assertCount(1, $this->attempts());
    }

    public function test_original_omission_is_preserved_and_audited_without_resolving_a_ladder(): void
    {
        $this->prepare();
        $snapshot = $this->repository->snapshot($this->run->id)->toArray();
        $snapshot['participants'][0]['is_evaluable'] = false;
        $snapshot['participants'][0]['total_points'] = '120.12345678';
        DB::table('progression_runs')->where('id', $this->run->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->mock(CaptureProgressionLadderPort::class)->shouldNotReceive('execute');
        app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now);
        self::assertSame('skipped', DB::table('progression_run_results')->where('id', $this->result->id)->value('status'));
        self::assertSame('skipped', $this->attempts()[0]['outcome']);
        self::assertSame('120.12345678', $this->attempts()[0]['total_points']);
        self::assertSame('120.12345678', ExactDecimal::fromString(DB::table('progression_run_results')->where('id', $this->result->id)->value('total_points'))->value());
        self::assertNull($this->attempts()[0]['ladder']);
        self::assertSame(0, DB::table('progression_placement_applications')->count());
    }

    public static function drivers(): array
    {
        return [['memory'], ['postgresql']];
    }

    #[DataProvider('drivers')]
    public function test_snapshot_points_are_copied_and_current_ladder_is_captured_once(string $driver): void
    {
        $this->prepare($driver);
        $capture = $this->mock(CaptureProgressionLadderPort::class);
        $capture->shouldReceive('execute')->once()->andReturn(new ProgressionLadderData([
            ['program_id' => $this->first->id, 'position' => 1, 'entry_threshold' => '0'],
            ['program_id' => $this->second->id, 'position' => 2, 'entry_threshold' => '200'],
        ]));
        $service = app(PrepareProgressionRecoveryService::class);
        $service->begin();
        $attempt = $service->execute($this->repository, $this->result, $this->now, 'finalize');
        self::assertSame('120.12345678', $attempt->total_points);
        self::assertSame($this->first->id, $attempt->target_program_id);
        self::assertSame($attempt->id, $service->execute($this->repository, $this->result, $this->now, 'placement')->id);
        $failed = $this->repository->failedResults($this->run->id)[0]->result;
        self::assertSame('120.12345678', $failed->totalPoints->value());
        self::assertSame($this->first->id, $failed->targetProgramId);
    }

    public function test_ladder_failure_is_auditable_and_does_not_apply_original_target(): void
    {
        $this->prepare();
        $this->repository->prepareDecision($this->result, ExactDecimal::fromString('120.12345678'), $this->second->id, $this->now);
        $this->mock(CaptureProgressionLadderPort::class)->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Private error'));
        $report = app(RecoverProgressionRunsUseCase::class)->execute($this->run->id, $this->now);
        self::assertSame(1, $report->results_failed);
        self::assertSame('ladder_unavailable', $this->attempts()[0]['failure_code']);
        self::assertNull($this->attempts()[0]['ladder']);
        self::assertNull($this->attempts()[0]['target_program_id']);
        self::assertSame(0, DB::table('progression_placement_applications')->count());
    }
}
