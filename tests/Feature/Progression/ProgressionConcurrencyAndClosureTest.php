<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramModuleSelectionRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Factories\ProgressionRunRepositoryFactory;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\Services\ProgressionExecutionMutex;
use App\Features\Progression\UseCases\RecoverProgressionRunsUseCase;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Repositories\PostgreSql\Models\RuleAssignmentRecord;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

/**
 * Cierre PG1 sesión 5: carrera real de idempotencia, atomicidad bajo contención
 * y evidencia de que PG1 no calcula runs ni muta placement.
 */
final class ProgressionConcurrencyAndClosureTest extends TestCase
{
    use DatabaseTruncation;

    private string $moduleId;

    private string $subscriptionId;

    private string $planId;

    private string $programId;

    private string $ruleId;

    private string $ruleVersionId;

    private string $ruleAssignmentId;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency races require PostgreSQL.');
        }

        $this->artisan('modules:sync')->assertExitCode(0);
        config()->set('progression.repository', 'postgresql');
        $this->seedDependencies();
    }

    protected function tearDown(): void
    {
        DB::table('progression_placement_applications')->delete();
        DB::table('progression_run_results')->delete();
        DB::table('progression_runs')->delete();
        DB::table('progression_contributions')->delete();
        DB::table('progression_activity_evaluations')->delete();
        DB::table('rule_assignments')->delete();
        DB::table('rule_versions')->delete();
        DB::table('rules')->delete();
        DB::table('subscription_placements')->delete();
        DB::table('subscription_changes')->delete();
        DB::table('subscriptions')->delete();
        DB::table('program_module_selections')->delete();
        DB::table('programs')->delete();
        DB::table('plan_operational_changes')->delete();
        DB::table('plan_module_bindings')->delete();
        DB::table('plans')->delete();

        try {
            DB::disconnect('pgsql_racing');
        } catch (Throwable) {
        }

        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_concurrent_record_contention_resolves_to_a_single_canonical_evaluation(): void
    {
        $beneficiaryId = (string) Str::uuid7();
        $sourceActivityId = 'concurrent-source';
        $now = CarbonImmutable::parse('2026-09-17T12:00:00Z');
        $quantity = ExactDecimal::fromString('100');
        $weight = ExactDecimal::fromString('0.1');
        $window = ProgressionWindow::of(
            CarbonImmutable::parse('2026-09-17T00:00:00Z'),
            CarbonImmutable::parse('2026-09-18T00:00:00Z'),
        );

        $canonicalId = (string) Str::uuid7();
        $contributionId = (string) Str::uuid7();

        $racing = $this->racingConnection();
        $racing->beginTransaction();

        try {
            $racing->table('progression_activity_evaluations')->insert([
                'id' => $canonicalId,
                'module_id' => $this->moduleId,
                'source_activity_id' => $sourceActivityId,
                'beneficiary_external_user_id' => $beneficiaryId,
                'subscription_id' => $this->subscriptionId,
                'plan_id' => $this->planId,
                'program_id' => $this->programId,
                'occurred_at' => $now,
                'window_starts_at' => $window->startsAt,
                'window_ends_at' => $window->endsAt,
                'metric_code' => 'confirmed_deposit',
                'unit_code' => 'usd',
                'instrument_reference' => null,
                'quantity' => $quantity->value(),
                'status' => 'accepted',
                'exclusion_reason' => null,
                'evaluated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $racing->table('progression_contributions')->insert([
                'id' => $contributionId,
                'evaluation_id' => $canonicalId,
                'rule_id' => $this->ruleId,
                'rule_version_id' => $this->ruleVersionId,
                'rule_assignment_id' => $this->ruleAssignmentId,
                'strategy_type' => 'points_per_quantity_unit',
                'scope_type' => 'all',
                'weight' => $weight->value(),
                'points' => '10',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $retryId = (string) Str::uuid7();
            $retry = ActivityEvaluation::accepted([
                'id' => $retryId,
                'moduleId' => $this->moduleId,
                'sourceActivityId' => $sourceActivityId,
                'beneficiaryExternalUserId' => $beneficiaryId,
                'subscriptionId' => $this->subscriptionId,
                'planId' => $this->planId,
                'programId' => $this->programId,
                'occurredAt' => $now,
                'window' => $window,
                'metricCode' => 'confirmed_deposit',
                'unitCode' => 'usd',
                'instrumentReference' => null,
                'quantity' => ExactDecimal::fromString('50'),
                'evaluatedAt' => $now,
                'contribution' => Contribution::create(
                    id: (string) Str::uuid7(),
                    evaluationId: $retryId,
                    ruleId: $this->ruleId,
                    ruleVersionId: $this->ruleVersionId,
                    ruleAssignmentId: $this->ruleAssignmentId,
                    quantity: ExactDecimal::fromString('50'),
                    weight: ExactDecimal::fromString('0.2'),
                    now: $now,
                ),
            ]);

            $repository = app(ActivityEvaluationRepositoryFactory::class)->make('postgresql');

            DB::statement("SET lock_timeout TO '500ms'");

            try {
                $repository->record($retry);
                self::fail('Expected a PostgreSQL lock timeout while the racing insert held the unique key.');
            } catch (Throwable $exception) {
                $this->assertMatchesRegularExpression('/lock|timeout|canceling statement/i', $exception->getMessage());
            } finally {
                try {
                    DB::rollBack();
                } catch (Throwable) {
                }
                DB::statement('SET lock_timeout TO 0');
            }

            $racing->commit();
        } catch (Throwable $exception) {
            $racing->rollBack();
            throw $exception;
        }

        $repository = app(ActivityEvaluationRepositoryFactory::class)->make('postgresql');
        $retryId = (string) Str::uuid7();
        $returned = $repository->record(ActivityEvaluation::accepted([
            'id' => $retryId,
            'moduleId' => $this->moduleId,
            'sourceActivityId' => $sourceActivityId,
            'beneficiaryExternalUserId' => $beneficiaryId,
            'subscriptionId' => $this->subscriptionId,
            'planId' => $this->planId,
            'programId' => $this->programId,
            'occurredAt' => $now,
            'window' => $window,
            'metricCode' => 'confirmed_deposit',
            'unitCode' => 'usd',
            'instrumentReference' => null,
            'quantity' => ExactDecimal::fromString('50'),
            'evaluatedAt' => $now,
            'contribution' => Contribution::create(
                id: (string) Str::uuid7(),
                evaluationId: $retryId,
                ruleId: $this->ruleId,
                ruleVersionId: $this->ruleVersionId,
                ruleAssignmentId: $this->ruleAssignmentId,
                quantity: ExactDecimal::fromString('50'),
                weight: ExactDecimal::fromString('0.2'),
                now: $now,
            ),
        ]));

        self::assertSame($canonicalId, $returned->id);
        self::assertSame('10', $returned->contribution?->points->value());
        self::assertSame(1, DB::table('progression_activity_evaluations')
            ->where('module_id', $this->moduleId)
            ->where('source_activity_id', $sourceActivityId)
            ->where('beneficiary_external_user_id', $beneficiaryId)
            ->count());
        self::assertSame(1, DB::table('progression_contributions')
            ->where('evaluation_id', $canonicalId)
            ->count());
        self::assertSame(0, DB::table('progression_activity_evaluations')->where('id', $retryId)->count());
    }

    public function test_pg2_schema_adds_runs_without_placement_mutation_tables(): void
    {
        self::assertTrue(Schema::hasTable('progression_activity_evaluations'));
        self::assertTrue(Schema::hasTable('progression_contributions'));
        self::assertTrue(Schema::hasTable('progression_runs'));
        self::assertTrue(Schema::hasTable('progression_run_results'));
        self::assertFalse(Schema::hasTable('progression_window_closures'));
    }

    public function test_run_and_result_constraints_are_idempotent_and_completed_runs_do_not_reopen(): void
    {
        $now = CarbonImmutable::parse('2026-09-20T01:00:00Z');
        $window = ProgressionWindow::of(
            CarbonImmutable::parse('2026-09-19T00:00:00Z'),
            CarbonImmutable::parse('2026-09-20T00:00:00Z'),
        );
        $repository = app(ProgressionRunRepositoryFactory::class)->make('postgresql');

        $first = $repository->findOrCreateRun($this->planId, $window, $now);
        $second = $repository->findOrCreateRun($this->planId, $window, $now->addSecond());
        self::assertSame($first->id, $second->id);
        self::assertSame(1, DB::table('progression_runs')->where('plan_id', $this->planId)->count());

        $firstResult = $repository->findOrCreateResult($first->id, $this->subscriptionId, $now);
        $secondSubscriptionId = $this->createActiveSubscription($now);
        $failedResult = $repository->findOrCreateResult($first->id, $secondSubscriptionId, $now);
        $repository->markCompleted($firstResult, ExactDecimal::fromString('12.5'), $this->programId, $now);
        $repository->markFailed($firstResult, 'must not overwrite final result', $now);
        $repository->markFailed($failedResult, 'retryable failure', $now);

        $withErrors = $repository->finishRun($first, $now);
        self::assertSame('completed_with_errors', $withErrors->status->value);
        self::assertSame('completed', DB::table('progression_run_results')->where('id', $firstResult->id)->value('status'));
        self::assertSame(1, DB::table('progression_run_results')->where('id', $failedResult->id)->value('attempt_count'));

        $retry = $repository->findOrCreateResult($first->id, $secondSubscriptionId, $now->addMinute());
        $repository->markCompleted($retry, ExactDecimal::fromString('0'), $this->programId, $now->addMinute());
        $completed = $repository->finishRun($withErrors, $now->addMinute());
        $repository->markFailed($retry, 'completed results remain final', $now->addMinutes(2));

        self::assertTrue($completed->isCompleted());
        self::assertSame('completed', DB::table('progression_runs')->where('id', $first->id)->value('status'));
        self::assertSame('completed', DB::table('progression_run_results')->where('id', $retry->id)->value('status'));
    }

    public function test_final_result_placement_application_is_idempotent_in_postgresql(): void
    {
        $now = CarbonImmutable::parse('2026-09-20T01:00:00Z');
        $window = ProgressionWindow::of($now->subDay(), $now);
        $repository = app(ProgressionRunRepositoryFactory::class)->make('postgresql');
        $run = $repository->findOrCreateRun($this->planId, $window, $now);
        $result = $repository->findOrCreateResult($run->id, $this->subscriptionId, $now);
        $repository->markCompleted($result, ExactDecimal::fromString('0'), $this->programId, $now);

        $final = $repository->finalizedResultsAwaitingPlacement();
        self::assertCount(1, $final);
        self::assertSame($result->id, $final[0]->id);

        $repository->transaction(function () use ($repository, $result, $now): void {
            self::assertNotNull($repository->lockFinalizedResultAwaitingPlacement($result->id));
            $repository->recordPlacementApplication($result->id, ProgressionPlacementOutcome::Unchanged, $now);
        });

        self::assertSame(1, DB::table('progression_placement_applications')->where('run_result_id', $result->id)->where('outcome', 'unchanged')->count());
        self::assertSame([], $repository->finalizedResultsAwaitingPlacement());
    }

    public function test_recovery_retries_only_failed_results_and_applies_the_pending_placement(): void
    {
        $now = CarbonImmutable::parse('2026-09-20T01:00:00Z');
        $window = ProgressionWindow::of($now->subDay(), $now);
        $repository = app(ProgressionRunRepositoryFactory::class)->make('postgresql');
        $run = $repository->findOrCreateRun($this->planId, $window, $now);
        $result = $repository->findOrCreateResult($run->id, $this->subscriptionId, $now);
        DB::table('subscription_placements')->insert([
            'id' => (string) Str::uuid7(),
            'subscription_id' => $this->subscriptionId,
            'program_id' => $this->programId,
            'is_fixed' => false,
            'effective_from' => $now->subDay(),
            'effective_until' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $repository->markFailed($result, 'retryable_failure', $now);

        DB::table('progression_runs')->where('id', $run->id)->update(['snapshot_generation' => 0]);
        $recovered = app(RecoverProgressionRunsUseCase::class)->execute($run->id, $now->addMinute());

        self::assertSame(1, $recovered->results_recovered);
        self::assertTrue($repository->snapshot($run->id)->legacy);
        self::assertSame(0, $recovered->results_failed);
        self::assertSame('completed', DB::table('progression_runs')->where('id', $run->id)->value('status'));
        self::assertSame('completed', DB::table('progression_run_results')->where('id', $result->id)->value('status'));
        self::assertSame(1, DB::table('progression_placement_applications')->where('run_result_id', $result->id)->count());
    }

    public function test_postgresql_execution_mutex_excludes_a_second_connection(): void
    {
        $first = new ProgressionExecutionMutex(DB::connection());
        $second = new ProgressionExecutionMutex($this->racingConnection());

        self::assertTrue($first->acquire());

        try {
            self::assertFalse($second->acquire());
        } finally {
            $first->release();
        }

        self::assertTrue($second->acquire());
        $second->release();
    }

    public function test_postgresql_run_contention_keeps_a_single_canonical_window(): void
    {
        $now = CarbonImmutable::parse('2026-09-20T01:00:00Z');
        $window = ProgressionWindow::of(
            CarbonImmutable::parse('2026-09-19T00:00:00Z'),
            CarbonImmutable::parse('2026-09-20T00:00:00Z'),
        );
        $canonicalId = (string) Str::uuid7();
        $racing = $this->racingConnection();
        $racing->beginTransaction();

        try {
            $racing->table('progression_runs')->insert([
                'id' => $canonicalId, 'plan_id' => $this->planId, 'window_starts_at' => $window->startsAt,
                'window_ends_at' => $window->endsAt, 'status' => 'pending', 'started_at' => $now,
                'completed_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::statement("SET lock_timeout TO '500ms'");
            try {
                app(ProgressionRunRepositoryFactory::class)->make('postgresql')->findOrCreateRun($this->planId, $window, $now);
                self::fail('Expected the contested unique key to wait for PostgreSQL.');
            } catch (Throwable $exception) {
                self::assertMatchesRegularExpression('/lock|timeout|canceling statement/i', $exception->getMessage());
            } finally {
                DB::rollBack();
                DB::statement('SET lock_timeout TO 0');
            }
            $racing->commit();
        } catch (Throwable $exception) {
            $racing->rollBack();
            throw $exception;
        }

        $run = app(ProgressionRunRepositoryFactory::class)->make('postgresql')->findOrCreateRun($this->planId, $window, $now);
        self::assertSame($canonicalId, $run->id);
        self::assertSame(1, DB::table('progression_runs')->where('plan_id', $this->planId)->count());
    }

    public function test_it_sums_only_final_accepted_contributions_for_subscription_and_window(): void
    {
        $window = ProgressionWindow::of(
            CarbonImmutable::parse('2026-09-19T00:00:00Z'),
            CarbonImmutable::parse('2026-09-20T00:00:00Z'),
        );
        $now = CarbonImmutable::parse('2026-09-20T01:00:00Z');
        $this->insertAcceptedContribution($this->subscriptionId, $window, CarbonImmutable::parse('2026-09-19T12:00:00Z'), '3.25');
        $this->insertAcceptedContribution($this->subscriptionId, $window, CarbonImmutable::parse('2026-09-19T18:00:00Z'), '6.75');
        $this->insertAcceptedContribution($this->createActiveSubscription($now), $window, CarbonImmutable::parse('2026-09-19T19:00:00Z'), '99');
        $nextWindow = ProgressionWindow::of($window->endsAt, $window->endsAt->addDay());
        $this->insertAcceptedContribution($this->subscriptionId, $nextWindow, CarbonImmutable::parse('2026-09-20T00:30:00Z'), '50');

        $points = app(ProgressionRunRepositoryFactory::class)->make('postgresql')
            ->sumAcceptedContributionPoints($this->planId, $this->subscriptionId, $window);

        self::assertSame('10', $points->value());
    }

    private function racingConnection(): Connection
    {
        config([
            'database.connections.pgsql_racing' => config('database.connections.pgsql_testing'),
        ]);

        return DB::connection('pgsql_racing');
    }

    private function seedDependencies(): void
    {
        $now = now('UTC');
        $plan = PlanRecord::factory()->create();
        $module = ModuleRecord::query()->where('code', 'broker')->firstOrFail();

        PlanModuleBindingRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'plan_id' => $plan->id,
            'module_id' => $module->id,
            'created_at' => $now,
        ]);

        $program = ProgramRecord::factory()->create([
            'plan_id' => $plan->id,
            'position' => 1,
            'entry_threshold' => 0,
        ]);
        ProgramModuleSelectionRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'program_id' => $program->id,
            'module_id' => $module->id,
            'created_at' => $now,
        ]);

        $subscriptionId = (string) Str::uuid7();
        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'external_user_id' => (string) Str::uuid7(),
            'plan_id' => $plan->id,
            'origin' => 'user_application',
            'requires_approval' => false,
            'status' => 'active',
            'activated_at' => $now,
            'closed_at' => null,
            'replaces_subscription_id' => null,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rule = RuleRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'plan_id' => $plan->id,
            'name' => 'Concurrent Points',
            'slug' => 'concurrent-points',
            'description' => null,
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ruleVersion = RuleVersionRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'rule_id' => $rule->id,
            'version_number' => 1,
            'status' => RuleVersionStatus::Published->value,
            'schema_version' => 1,
            'configuration' => ['unit' => 'usd', 'points_per_unit' => '0.1'],
            'published_at' => $now,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $assignment = RuleAssignmentRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'rule_id' => $rule->id,
            'rule_version_id' => $ruleVersion->id,
            'program_id' => $program->id,
            'module_id' => $module->id,
            'scope_type' => RuleAssignmentScopeType::All->value,
            'starts_at' => $now,
            'ends_at' => null,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->moduleId = (string) $module->id;
        $this->subscriptionId = $subscriptionId;
        $this->planId = (string) $plan->id;
        $this->programId = (string) $program->id;
        $this->ruleId = (string) $rule->id;
        $this->ruleVersionId = (string) $ruleVersion->id;
        $this->ruleAssignmentId = (string) $assignment->id;
    }

    private function createActiveSubscription(CarbonImmutable $now): string
    {
        $id = (string) Str::uuid7();
        DB::table('subscriptions')->insert([
            'id' => $id, 'external_user_id' => (string) Str::uuid7(), 'plan_id' => $this->planId,
            'origin' => 'user_application', 'requires_approval' => false, 'status' => 'active',
            'activated_at' => $now, 'closed_at' => null, 'replaces_subscription_id' => null,
            'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }

    private function insertAcceptedContribution(string $subscriptionId, ProgressionWindow $window, CarbonImmutable $occurredAt, string $points): void
    {
        $evaluationId = (string) Str::uuid7();
        $now = CarbonImmutable::parse('2026-09-20T01:00:00Z');
        DB::table('progression_activity_evaluations')->insert([
            'id' => $evaluationId, 'module_id' => $this->moduleId, 'source_activity_id' => (string) Str::uuid7(),
            'beneficiary_external_user_id' => (string) Str::uuid7(), 'subscription_id' => $subscriptionId,
            'plan_id' => $this->planId, 'program_id' => $this->programId, 'occurred_at' => $occurredAt,
            'window_starts_at' => $window->startsAt, 'window_ends_at' => $window->endsAt,
            'metric_code' => 'confirmed_deposit', 'unit_code' => 'usd', 'instrument_reference' => null,
            'quantity' => '1', 'status' => 'accepted', 'exclusion_reason' => null,
            'evaluated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('progression_contributions')->insert([
            'id' => (string) Str::uuid7(), 'evaluation_id' => $evaluationId, 'rule_id' => $this->ruleId,
            'rule_version_id' => $this->ruleVersionId, 'rule_assignment_id' => $this->ruleAssignmentId,
            'strategy_type' => 'points_per_quantity_unit', 'scope_type' => 'all', 'weight' => '1',
            'distribution_weight' => '1', 'points' => $points, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
