<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class CpaRewardCalculationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ended_subscription_and_new_plan_do_not_replace_cpa_context_or_duplicate_reward(): void
    {
        $now = CarbonImmutable::now('UTC');
        $captured = $now->subDays(2);
        $user = (string) Str::uuid7();
        $referred = (string) Str::uuid7();
        $plan = PlanRecord::factory()->create();
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $otherProgram = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 2, 'entry_threshold' => 100]);
        $destination = PlanRecord::factory()->create();
        $destinationProgram = ProgramRecord::factory()->create(['plan_id' => $destination->id, 'position' => 1, 'entry_threshold' => 0]);
        $module = ModuleRecord::query()->where('code', 'broker')->first() ?? ModuleRecord::factory()->create(['code' => 'broker']);
        $rule = RuleRecord::query()->create(['id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'CPA', 'slug' => 'cpa', 'strategy_type' => 'cpa_fixed_amount', 'lock_version' => 1, 'created_at' => $captured, 'updated_at' => $captured]);
        $version = RuleVersionRecord::query()->create(['id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1, 'status' => 'published', 'schema_version' => 1, 'configuration' => ['currency_precision' => 2], 'published_at' => $captured, 'lock_version' => 1, 'created_at' => $captured, 'updated_at' => $captured]);
        $assignmentId = (string) Str::uuid7();
        DB::table('rule_assignments')->insert(['id' => $assignmentId, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'program_id' => $program->id, 'module_id' => $module->id, 'scope_type' => 'all', 'starts_at' => $captured, 'ends_at' => null, 'lock_version' => 1, 'created_at' => $captured, 'updated_at' => $captured]);
        $oldSubscription = (string) Str::uuid7();
        DB::table('subscriptions')->insert(['id' => $oldSubscription, 'external_user_id' => $user, 'plan_id' => $plan->id, 'origin' => 'user_application', 'requires_approval' => false, 'status' => 'ended', 'activated_at' => $captured, 'closed_at' => $now->subDay(), 'lock_version' => 1, 'created_at' => $captured, 'updated_at' => $now]);
        DB::table('subscription_placements')->insert([
            ['id' => (string) Str::uuid7(), 'subscription_id' => $oldSubscription, 'program_id' => $program->id, 'is_fixed' => false, 'effective_from' => $captured, 'effective_until' => $now->subHours(36), 'created_at' => $captured, 'updated_at' => $now],
            ['id' => (string) Str::uuid7(), 'subscription_id' => $oldSubscription, 'program_id' => $otherProgram->id, 'is_fixed' => false, 'effective_from' => $now->subHours(36), 'effective_until' => $now->subDay(), 'created_at' => $captured, 'updated_at' => $now],
        ]);
        $newSubscription = (string) Str::uuid7();
        DB::table('subscriptions')->insert(['id' => $newSubscription, 'external_user_id' => $user, 'plan_id' => $destination->id, 'origin' => 'admin_plan_change', 'requires_approval' => null, 'replaces_subscription_id' => $oldSubscription, 'status' => 'active', 'activated_at' => $now->subDay(), 'lock_version' => 1, 'created_at' => $now->subDay(), 'updated_at' => $now]);
        DB::table('subscription_placements')->insert(['id' => (string) Str::uuid7(), 'subscription_id' => $newSubscription, 'program_id' => $destinationProgram->id, 'is_fixed' => false, 'effective_from' => $now->subDay(), 'effective_until' => null, 'created_at' => $now->subDay(), 'updated_at' => $now]);
        $contextId = (string) Str::uuid7();
        $requirements = ['required_volume' => '0', 'required_deposit_minor' => 100, 'amount' => '25.00', 'currency_code' => 'USD', 'currency_precision' => 2];
        DB::table('cpa_contexts')->insert(['id' => $contextId, 'ib_user_id' => $user, 'referred_user_id' => $referred, 'plan_id' => $plan->id, 'program_id' => $program->id, 'module_id' => $module->id, 'rule_assignment_id' => $assignmentId, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'symbols_snapshot' => '[]', 'requirements_snapshot' => json_encode($requirements, JSON_THROW_ON_ERROR), 'captured_at' => $captured]);
        DB::table('cpa_verification_progress')->insert(['id' => (string) Str::uuid7(), 'cpa_context_id' => $contextId, 'ib_user_id' => $user, 'referred_user_id' => $referred, 'status' => 'pending', 'observed_volume' => '0', 'required_volume' => '0', 'volume_unit_code' => 'lot', 'observed_deposit_minor' => 0, 'required_deposit_minor' => 100, 'currency_code' => 'USD', 'volume_satisfied' => false, 'deposit_satisfied' => false, 'observed_from' => $captured, 'created_at' => $captured, 'updated_at' => $captured]);
        $original = DB::table('cpa_contexts')->where('id', $contextId)->first();
        $evidence = new CpaEvidenceData([], [['source_activity_id' => 'deposit', 'subject_external_user_id' => $referred, 'amount_minor' => 100, 'currency_code' => 'USD', 'occurred_at' => $now->subHour()->toISOString()]]);
        $modules = Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->once()->andReturn([new ModuleSummaryData($module->id, 'broker', 'Broker', true, 'running')]);
        app()->instance(ResolveModulesPort::class, $modules);
        $provider = Mockery::mock(ListCpaEvidencePort::class);
        $provider->shouldReceive('list')->once()->andReturn($evidence);
        app()->instance(ListCpaEvidencePort::class, $provider);
        config()->set('rewards.cpa.incremental_evidence', false);
        app()->forgetInstance(VerifyCpaContextsUseCase::class);
        $taskResult = app(VerifyCpaContextsUseCase::class)->execute(10);
        self::assertSame(1, $taskResult['qualified'], json_encode($taskResult));
        self::assertSame(0, app(VerifyCpaContextsUseCase::class)->execute(10)['qualified']);
        app(RewardRepositoryFactory::class)->make()->persistQualifiedCpaContext($original, $requirements, $evidence, '0', 100, $now, true);
        $reward = DB::table('rewards')->sole();
        self::assertSame($plan->id, $reward->plan_id);
        self::assertSame($program->id, $reward->program_id);
        self::assertSame($version->id, $reward->rule_version_id);
        self::assertSame(2500, (int) $reward->amount_minor);
        self::assertSame($reward->id, DB::table('cpa_contexts')->where('id', $contextId)->value('reward_id'));
        self::assertSame(1, DB::table('reward_evidence')->count());
        self::assertSame($original->requirements_snapshot, DB::table('cpa_contexts')->where('id', $contextId)->value('requirements_snapshot'));
    }
}
