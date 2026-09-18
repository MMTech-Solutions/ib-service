<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramModuleSelectionRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\Support\ExclusionExplanation;
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
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class ActivityEvaluationEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private string $moduleId;

    private string $subscriptionId;

    private string $planId;

    private string $programId;

    private string $ruleId;

    private string $ruleVersionId;

    private string $ruleAssignmentId;

    private string $acceptedEvaluationId;

    private string $excludedEvaluationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->seedFixtures();
    }

    public function test_authorized_operator_can_list_and_filter_evaluations(): void
    {
        $response = $this->gatewayJson('GET', '/api/ib/v1/admin/activity-evaluations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.filters', []);

        $accepted = collect($response->json('data'))->firstWhere('status', 'accepted');
        self::assertNotNull($accepted);
        self::assertSame('10', $accepted['contribution']['points']);
        self::assertNull($accepted['exclusion_reason']);
        self::assertNull($accepted['exclusion_explanation']);

        $this->gatewayJson(
            'GET',
            '/api/ib/v1/admin/activity-evaluations?'.http_build_query([
                'status' => 'excluded',
                'exclusion_reason' => ExclusionReason::NoApplicableRule->value,
                'plan_id' => $this->planId,
                'subscription_id' => $this->subscriptionId,
                'occurred_at_from' => '2026-09-17T00:00:00Z',
                'occurred_at_to' => '2026-09-17T23:59:59Z',
            ]),
        )->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->excludedEvaluationId)
            ->assertJsonPath('data.0.exclusion_reason', ExclusionReason::NoApplicableRule->value)
            ->assertJsonPath(
                'data.0.exclusion_explanation',
                ExclusionExplanation::for(ExclusionReason::NoApplicableRule),
            )
            ->assertJsonPath('data.0.contribution', null)
            ->assertJsonPath('meta.filters.status', 'excluded')
            ->assertJsonPath('meta.filters.exclusion_reason', ExclusionReason::NoApplicableRule->value);

        $this->gatewayJson('GET', '/api/ib/v1/admin/activity-evaluations?per_page=101')
            ->assertUnprocessable();
    }

    public function test_authorized_operator_can_show_accepted_and_excluded_evaluations(): void
    {
        $this->gatewayJson('GET', '/api/ib/v1/admin/activity-evaluations/'.$this->acceptedEvaluationId)
            ->assertOk()
            ->assertJsonPath('data.id', $this->acceptedEvaluationId)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.contribution.points', '10')
            ->assertJsonPath('data.exclusion_reason', null)
            ->assertJsonPath('data.exclusion_explanation', null);

        $this->gatewayJson('GET', '/api/ib/v1/admin/activity-evaluations/'.$this->excludedEvaluationId)
            ->assertOk()
            ->assertJsonPath('data.id', $this->excludedEvaluationId)
            ->assertJsonPath('data.status', 'excluded')
            ->assertJsonPath('data.exclusion_reason', ExclusionReason::NoApplicableRule->value)
            ->assertJsonPath(
                'data.exclusion_explanation',
                ExclusionExplanation::for(ExclusionReason::NoApplicableRule),
            )
            ->assertJsonPath('data.contribution', null);
    }

    public function test_unknown_evaluation_returns_not_found(): void
    {
        $this->gatewayJson('GET', '/api/ib/v1/admin/activity-evaluations/'.(string) Str::uuid7())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'PROGRESSION_EVALUATION_NOT_FOUND');
    }

    public function test_list_and_show_require_admin_permission_and_reject_customer_identity(): void
    {
        $this->assertGatewayAuthGuards('GET', '/api/ib/v1/admin/activity-evaluations');
        $this->assertGatewayAuthGuards(
            'GET',
            '/api/ib/v1/admin/activity-evaluations/'.$this->acceptedEvaluationId,
        );

        $this->seedAuthorizedCustomer();
        $this->customerGatewayJson('GET', '/api/ib/v1/admin/activity-evaluations')
            ->assertForbidden();
    }

    private function seedFixtures(): void
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
            'name' => 'Deposit Points',
            'slug' => 'deposit-points',
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

        $repository = app(ActivityEvaluationRepositoryFactory::class)->make('postgresql');
        $accepted = $this->acceptedEvaluation();
        $excluded = $this->excludedEvaluation();
        $repository->record($accepted);
        $repository->record($excluded);
        $this->acceptedEvaluationId = $accepted->id;
        $this->excludedEvaluationId = $excluded->id;
    }

    private function acceptedEvaluation(): ActivityEvaluation
    {
        $evaluationId = (string) Str::uuid7();
        $now = CarbonImmutable::parse('2026-09-17T12:00:00Z');
        $quantity = ExactDecimal::fromString('100');
        $weight = ExactDecimal::fromString('0.1');
        $contribution = Contribution::create(
            id: (string) Str::uuid7(),
            evaluationId: $evaluationId,
            ruleId: $this->ruleId,
            ruleVersionId: $this->ruleVersionId,
            ruleAssignmentId: $this->ruleAssignmentId,
            quantity: $quantity,
            weight: $weight,
            now: $now,
        );

        return ActivityEvaluation::accepted([
            'id' => $evaluationId,
            'moduleId' => $this->moduleId,
            'sourceActivityId' => 'admin-list-accepted',
            'beneficiaryExternalUserId' => (string) Str::uuid7(),
            'subscriptionId' => $this->subscriptionId,
            'planId' => $this->planId,
            'programId' => $this->programId,
            'occurredAt' => $now,
            'window' => ProgressionWindow::of(
                CarbonImmutable::parse('2026-09-17T00:00:00Z'),
                CarbonImmutable::parse('2026-09-18T00:00:00Z'),
            ),
            'metricCode' => 'deposit_confirmed',
            'unitCode' => 'usd',
            'instrumentReference' => null,
            'quantity' => $quantity,
            'evaluatedAt' => $now,
            'contribution' => $contribution,
        ]);
    }

    private function excludedEvaluation(): ActivityEvaluation
    {
        $now = CarbonImmutable::parse('2026-09-17T15:00:00Z');

        return ActivityEvaluation::excluded([
            'id' => (string) Str::uuid7(),
            'moduleId' => $this->moduleId,
            'sourceActivityId' => 'admin-list-excluded',
            'beneficiaryExternalUserId' => (string) Str::uuid7(),
            'subscriptionId' => $this->subscriptionId,
            'planId' => $this->planId,
            'programId' => $this->programId,
            'occurredAt' => $now,
            'window' => ProgressionWindow::of(
                CarbonImmutable::parse('2026-09-17T00:00:00Z'),
                CarbonImmutable::parse('2026-09-18T00:00:00Z'),
            ),
            'metricCode' => 'deposit_confirmed',
            'unitCode' => 'usd',
            'instrumentReference' => null,
            'quantity' => ExactDecimal::fromString('50'),
            'exclusionReason' => ExclusionReason::NoApplicableRule,
            'evaluatedAt' => $now,
        ]);
    }
}
