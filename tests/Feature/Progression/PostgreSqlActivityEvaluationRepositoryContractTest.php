<?php

declare(strict_types=1);

namespace Tests\Feature\Progression;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramModuleSelectionRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Progression\Contracts\Repositories\ActivityEvaluationRepositoryInterface;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Repositories\PostgreSql\Models\RuleAssignmentRecord;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Contracts\ActivityEvaluationRepositoryContract;

final class PostgreSqlActivityEvaluationRepositoryContractTest extends ActivityEvaluationRepositoryContract
{
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
        $this->artisan('modules:sync')->assertExitCode(0);

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
    }

    protected function repository(): ActivityEvaluationRepositoryInterface
    {
        return app(ActivityEvaluationRepositoryFactory::class)->make('postgresql');
    }

    protected function moduleId(): string
    {
        return $this->moduleId;
    }

    protected function subscriptionId(): string
    {
        return $this->subscriptionId;
    }

    protected function planId(): string
    {
        return $this->planId;
    }

    protected function programId(): string
    {
        return $this->programId;
    }

    protected function ruleId(): string
    {
        return $this->ruleId;
    }

    protected function ruleVersionId(): string
    {
        return $this->ruleVersionId;
    }

    protected function ruleAssignmentId(): string
    {
        return $this->ruleAssignmentId;
    }
}
