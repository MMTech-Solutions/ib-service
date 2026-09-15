<?php

declare(strict_types=1);

namespace Tests\Feature\Rules\Assignments;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramModuleSelectionRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Illuminate\Support\Str;
use Tests\Contracts\RuleAssignmentRepositoryContract;

final class PostgreSqlRuleAssignmentRepositoryContractTest extends RuleAssignmentRepositoryContract
{
    private string $ruleId;

    private string $programId;

    private string $moduleId;

    private string $ruleVersionId;

    private string $alternateRuleVersionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('modules:sync')->assertExitCode(0);

        $plan = PlanRecord::factory()->create();
        $module = ModuleRecord::query()->where('code', 'broker')->firstOrFail();
        PlanModuleBindingRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'plan_id' => $plan->id,
            'module_id' => $module->id,
            'created_at' => now('UTC'),
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
            'created_at' => now('UTC'),
        ]);

        $now = now('UTC');
        $rule = RuleRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'plan_id' => $plan->id,
            'name' => 'CPA Standard',
            'slug' => 'cpa-standard',
            'description' => null,
            'strategy_type' => RuleStrategyType::PointsPerQuantityUnit->value,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $firstVersion = RuleVersionRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'rule_id' => $rule->id,
            'version_number' => 1,
            'status' => RuleVersionStatus::Published->value,
            'schema_version' => 1,
            'configuration' => ['unit' => 'lot', 'points_per_unit' => '50.00'],
            'published_at' => $now,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $secondVersion = RuleVersionRecord::query()->create([
            'id' => (string) Str::uuid7(),
            'rule_id' => $rule->id,
            'version_number' => 2,
            'status' => RuleVersionStatus::Published->value,
            'schema_version' => 1,
            'configuration' => ['unit' => 'lot', 'points_per_unit' => '75.00'],
            'published_at' => $now,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->ruleId = (string) $rule->id;
        $this->programId = (string) $program->id;
        $this->moduleId = (string) $module->id;
        $this->ruleVersionId = (string) $firstVersion->id;
        $this->alternateRuleVersionId = (string) $secondVersion->id;
    }

    protected function repository(): RuleAssignmentRepositoryInterface
    {
        return app(RuleAssignmentRepositoryFactory::class)->make('postgresql');
    }

    protected function ruleId(): string
    {
        return $this->ruleId;
    }

    protected function programId(): string
    {
        return $this->programId;
    }

    protected function moduleId(): string
    {
        return $this->moduleId;
    }

    protected function ruleVersionId(): string
    {
        return $this->ruleVersionId;
    }

    protected function alternateRuleVersionId(): string
    {
        return $this->alternateRuleVersionId;
    }
}
