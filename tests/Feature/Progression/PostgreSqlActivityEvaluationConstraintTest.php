<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramModuleSelectionRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Repositories\PostgreSql\Models\RuleAssignmentRecord;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

final class PostgreSqlActivityEvaluationConstraintTest extends TestCase
{
    use RefreshDatabase;

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
            $this->markTestSkipped('PostgreSQL constraints require a pgsql connection.');
        }

        $this->artisan('modules:sync')->assertExitCode(0);
        $this->seedDependencies();
    }

    public function test_it_rejects_invalid_status_shapes(): void
    {
        $this->expectExceptionMessageMatches('/progression_evaluations_status_check|check constraint/i');

        DB::table('progression_activity_evaluations')->insert($this->evaluationRow([
            'status' => 'pending',
            'exclusion_reason' => null,
        ]));
    }

    public function test_it_rejects_accepted_evaluations_without_context(): void
    {
        $this->expectExceptionMessageMatches('/progression_evaluations_accepted_context_check|check constraint/i');

        DB::table('progression_activity_evaluations')->insert($this->evaluationRow([
            'status' => 'accepted',
            'exclusion_reason' => null,
            'subscription_id' => null,
            'plan_id' => null,
            'program_id' => null,
            'window_starts_at' => null,
            'window_ends_at' => null,
        ]));
    }

    public function test_it_rejects_excluded_evaluations_without_reason(): void
    {
        $this->expectExceptionMessageMatches('/progression_evaluations_status_reason_shape_check|check constraint/i');

        DB::table('progression_activity_evaluations')->insert($this->evaluationRow([
            'status' => 'excluded',
            'exclusion_reason' => null,
            'subscription_id' => null,
            'plan_id' => null,
            'program_id' => null,
            'window_starts_at' => null,
            'window_ends_at' => null,
        ]));
    }

    public function test_it_rejects_unknown_exclusion_reasons(): void
    {
        $this->expectExceptionMessageMatches('/progression_evaluations_exclusion_reason_check|check constraint/i');

        DB::table('progression_activity_evaluations')->insert($this->evaluationRow([
            'status' => 'excluded',
            'exclusion_reason' => 'made_up_reason',
            'subscription_id' => null,
            'plan_id' => null,
            'program_id' => null,
            'window_starts_at' => null,
            'window_ends_at' => null,
        ]));
    }

    public function test_it_rejects_invalid_window_order(): void
    {
        $this->expectExceptionMessageMatches('/progression_evaluations_window_order_check|check constraint/i');

        $start = now('UTC');
        DB::table('progression_activity_evaluations')->insert($this->evaluationRow([
            'window_starts_at' => $start,
            'window_ends_at' => $start->copy()->subSecond(),
        ]));
    }

    public function test_it_rejects_duplicate_idempotency_keys(): void
    {
        $row = $this->evaluationRow([
            'source_activity_id' => 'dup-source',
            'beneficiary_external_user_id' => (string) Str::uuid7(),
        ]);
        DB::table('progression_activity_evaluations')->insert($row);

        $this->expectExceptionMessageMatches('/progression_evaluations_idempotency_unique|unique/i');

        DB::table('progression_activity_evaluations')->insert(array_merge($row, [
            'id' => (string) Str::uuid7(),
        ]));
    }

    public function test_it_rejects_invalid_contribution_strategy_and_scope(): void
    {
        $evaluationId = (string) Str::uuid7();
        DB::table('progression_activity_evaluations')->insert($this->evaluationRow([
            'id' => $evaluationId,
        ]));

        $this->expectExceptionMessageMatches('/progression_contributions_strategy_type_check|check constraint/i');

        DB::table('progression_contributions')->insert($this->contributionRow([
            'evaluation_id' => $evaluationId,
            'strategy_type' => 'other_strategy',
        ]));
    }

    public function test_foreign_keys_restrict_module_deletion(): void
    {
        DB::table('progression_activity_evaluations')->insert($this->evaluationRow());

        $this->expectExceptionMessageMatches('/foreign key|restrict/i');

        ModuleRecord::query()->whereKey($this->moduleId)->delete();
    }

    public function test_accepted_record_rolls_back_when_contribution_insert_fails(): void
    {
        $repository = app(ActivityEvaluationRepositoryFactory::class)->make('postgresql');
        $evaluationId = (string) Str::uuid7();
        $now = CarbonImmutable::parse('2026-09-17T12:00:00Z');
        $quantity = ExactDecimal::fromString('100');
        $weight = ExactDecimal::fromString('0.1');

        $contribution = Contribution::create(
            id: (string) Str::uuid7(),
            evaluationId: $evaluationId,
            ruleId: (string) Str::uuid7(),
            ruleVersionId: $this->ruleVersionId,
            ruleAssignmentId: $this->ruleAssignmentId,
            quantity: $quantity,
            weight: $weight,
            now: $now,
        );

        $evaluation = ActivityEvaluation::accepted([
            'id' => $evaluationId,
            'moduleId' => $this->moduleId,
            'sourceActivityId' => 'atomic-fail-source',
            'beneficiaryExternalUserId' => (string) Str::uuid7(),
            'subscriptionId' => $this->subscriptionId,
            'planId' => $this->planId,
            'programId' => $this->programId,
            'occurredAt' => $now,
            'window' => ProgressionWindow::of(
                CarbonImmutable::parse('2026-09-17T00:00:00Z'),
                CarbonImmutable::parse('2026-09-18T00:00:00Z'),
            ),
            'metricCode' => 'confirmed_deposit',
            'unitCode' => 'usd',
            'instrumentReference' => null,
            'quantity' => $quantity,
            'evaluatedAt' => $now,
            'contribution' => $contribution,
        ]);

        try {
            $repository->record($evaluation);
            self::fail('Expected contribution foreign key failure.');
        } catch (Throwable $exception) {
            $this->assertMatchesRegularExpression('/foreign key|SQLSTATE/i', $exception->getMessage());
        }

        self::assertNull($repository->findById($evaluationId));
        self::assertSame(0, DB::table('progression_activity_evaluations')->where('id', $evaluationId)->count());
        self::assertSame(0, DB::table('progression_contributions')->where('evaluation_id', $evaluationId)->count());
    }

    public function test_unique_collision_returns_canonical_without_recalculating(): void
    {
        $beneficiaryId = (string) Str::uuid7();
        $sourceActivityId = 'race-source';
        $now = CarbonImmutable::parse('2026-09-17T12:00:00Z');
        $quantity = ExactDecimal::fromString('100');
        $weight = ExactDecimal::fromString('0.1');
        $window = ProgressionWindow::of(
            CarbonImmutable::parse('2026-09-17T00:00:00Z'),
            CarbonImmutable::parse('2026-09-18T00:00:00Z'),
        );

        $firstId = (string) Str::uuid7();
        $canonical = ActivityEvaluation::accepted([
            'id' => $firstId,
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
            'quantity' => $quantity,
            'evaluatedAt' => $now,
            'contribution' => Contribution::create(
                id: (string) Str::uuid7(),
                evaluationId: $firstId,
                ruleId: $this->ruleId,
                ruleVersionId: $this->ruleVersionId,
                ruleAssignmentId: $this->ruleAssignmentId,
                quantity: $quantity,
                weight: $weight,
                now: $now,
            ),
        ]);

        $repository = app(ActivityEvaluationRepositoryFactory::class)->make('postgresql');
        $repository->record($canonical);

        $secondId = (string) Str::uuid7();
        $retry = ActivityEvaluation::accepted([
            'id' => $secondId,
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
                evaluationId: $secondId,
                ruleId: $this->ruleId,
                ruleVersionId: $this->ruleVersionId,
                ruleAssignmentId: $this->ruleAssignmentId,
                quantity: ExactDecimal::fromString('50'),
                weight: ExactDecimal::fromString('0.2'),
                now: $now,
            ),
        ]);

        $returned = $repository->record($retry);

        self::assertSame($firstId, $returned->id);
        self::assertSame('10', $returned->contribution?->points->value());
        self::assertSame(1, DB::table('progression_activity_evaluations')
            ->where('source_activity_id', $sourceActivityId)
            ->where('beneficiary_external_user_id', $beneficiaryId)
            ->count());
        self::assertSame(0, DB::table('progression_activity_evaluations')->where('id', $secondId)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function evaluationRow(array $overrides = []): array
    {
        $now = now('UTC');

        return array_merge([
            'id' => (string) Str::uuid7(),
            'module_id' => $this->moduleId,
            'source_activity_id' => 'constraint-source',
            'beneficiary_external_user_id' => (string) Str::uuid7(),
            'subscription_id' => $this->subscriptionId,
            'plan_id' => $this->planId,
            'program_id' => $this->programId,
            'occurred_at' => $now,
            'window_starts_at' => $now->copy()->startOfDay(),
            'window_ends_at' => $now->copy()->startOfDay()->addDay(),
            'metric_code' => 'confirmed_deposit',
            'unit_code' => 'usd',
            'instrument_reference' => null,
            'quantity' => '25.5',
            'status' => 'accepted',
            'exclusion_reason' => null,
            'evaluated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contributionRow(array $overrides = []): array
    {
        $now = now('UTC');

        return array_merge([
            'id' => (string) Str::uuid7(),
            'evaluation_id' => (string) Str::uuid7(),
            'rule_id' => $this->ruleId,
            'rule_version_id' => $this->ruleVersionId,
            'rule_assignment_id' => $this->ruleAssignmentId,
            'strategy_type' => 'points_per_quantity_unit',
            'scope_type' => 'all',
            'weight' => '0.1',
            'points' => '2.55',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
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
            'name' => 'Constraint Points',
            'slug' => 'constraint-points',
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
}
