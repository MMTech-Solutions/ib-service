<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Programs\Contracts\Data\V1\ProgramCpaSymbolData;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CpaFixtures
{
    /** @param list<string> $ids @return array<string,mixed> */
    private function cpaConfiguration(array $ids): array
    {
        return ['amount' => '25.00', 'currency' => 'EUR', 'currency_precision' => 2,
            'required_volume_points' => '100', 'required_deposit_points' => '100',
            'deposit_currency' => 'USD', 'deposit_currency_precision' => 2, 'deposit_points_per_unit' => '0.2',
            'expiration_days' => 365,
            'volume_modules' => array_map(static fn (string $id, int $index): array => ['module_id' => $id, 'unit' => 'lot', 'points_per_unit' => $index === 0 ? '20' : '60'], $ids, array_keys($ids))];
    }

    /** @return array<string,mixed> */
    private function cpaFixture(?string $ib = null, bool $twoModules = true): array
    {
        $at = now('UTC')->subDays(2)->toISOString();
        $ib ??= (string) Str::uuid7();
        $referred = (string) Str::uuid7();
        $plan = PlanRecord::factory()->create(['is_active' => true]);
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $broker = ModuleRecord::query()->where('code', 'broker')->first() ?? ModuleRecord::factory()->create(['code' => 'broker']);
        $modules = [$broker];
        if ($twoModules) {
            $modules[] = ModuleRecord::factory()->create(['code' => 'cpa-test']);
        }
        $configuration = $this->cpaConfiguration(array_map(static fn ($m): string => $m->id, $modules));
        $rule = RuleRecord::query()->create(['id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'CPA '.Str::uuid7(), 'slug' => 'cpa-'.Str::uuid7(), 'strategy_type' => 'cpa_fixed_amount', 'lock_version' => 1, 'created_at' => $at, 'updated_at' => $at]);
        $version = RuleVersionRecord::query()->create(['id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1, 'status' => 'published', 'schema_version' => 1, 'configuration' => $configuration, 'published_at' => $at, 'lock_version' => 1, 'created_at' => $at, 'updated_at' => $at]);
        $assignment = (string) Str::uuid7();
        DB::table('program_cpa_rule_assignments')->insert(['id' => $assignment, 'program_id' => $program->id, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'starts_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
        $symbols = [];
        foreach ($modules as $module) {
            DB::table('plan_module_bindings')->insert(['id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'module_id' => $module->id, 'created_at' => $at]);
            DB::table('program_module_selections')->insert(['id' => (string) Str::uuid7(), 'program_id' => $program->id, 'module_id' => $module->id, 'created_at' => $at]);
            $symbols[$module->id] = [new ProgramCpaSymbolData('symbol', 'group', 'USD')];
            DB::table('program_symbol_configurations')->insert(['id' => (string) Str::uuid7(), 'program_id' => $program->id, 'module_id' => $module->id, 'symbol_reference' => 'symbol', 'server_group_reference' => 'group', 'currency_code' => 'USD', 'use_for_cpa' => true, 'starts_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
        }
        $data = new CaptureCpaContextData($referred, $ib, $at);
        $repository = app(RewardRepositoryFactory::class)->make();
        $captured = $repository->captureCpaContext($data, (object) ['plan_id' => $plan->id, 'program_id' => $program->id], new CpaRuleContextData($assignment, $rule->id, $version->id, $configuration), $symbols, $configuration);
        $context = DB::table('cpa_contexts')->where('id', $captured['id'])->first();

        return compact('ib', 'referred', 'plan', 'program', 'modules', 'rule', 'version', 'assignment', 'configuration', 'data', 'repository', 'context', 'symbols');
    }
}
