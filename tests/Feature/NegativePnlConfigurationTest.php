<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveNegativePnlProgramConfigurationPort;
use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use App\Features\Rules\Contracts\Data\V1\ResolveNegativePnlRuleContextQueryData;
use App\Features\Rules\Contracts\Exceptions\InvalidNegativePnlRuleContextException;
use App\Features\Rules\Contracts\Ports\Input\ResolveNegativePnlRuleContextPort;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class NegativePnlConfigurationTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        Http::preventStrayRequests();
    }

    public function test_initial_configuration_is_absent_and_gateway_permissions_are_required(): void
    {
        $f = $this->fixture();
        $this->gatewayJson('GET', $f['url'])->assertOk()->assertJsonPath('data', []);
        $this->assertGatewayAuthGuards('GET', $f['url']);
        $this->assertGatewayAuthGuards('PUT', $f['url'], $f['payload']);
    }

    public function test_replace_identical_replace_retirement_and_actor_history(): void
    {
        $f = $this->fixture();
        $this->travelTo(now('UTC')->addSeconds(2));
        $first = $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk()->assertJsonPath('data.actor_id', $this->authorizedSub())->assertJsonPath('data.groups.0.rule_version_id', $f['version_id'])->json('data');
        $this->gatewayJson('GET', $f['url'])->assertOk()->assertJsonPath('data.id', $first['id'])->assertJsonMissingPath('data.configuration');
        $this->travel(1)->seconds();
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk()->assertJsonPath('data.id', $first['id']);
        self::assertSame(1, DB::table('program_negative_pnl_configuration_revisions')->count());
        $this->travel(1)->seconds();
        $second = $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'cadence' => 'weekly'])->assertOk()->json('data');
        self::assertNotSame($first['id'], $second['id']);
        $this->assertDatabaseHas('program_negative_pnl_configuration_revisions', ['id' => $first['id'], 'closed_by_actor_id' => $this->authorizedSub()]);
        $this->travel(1)->seconds();
        $this->gatewayJson('PUT', $f['url'], ['cadence' => 'monthly', 'groups' => []])->assertOk()->assertJsonPath('data', []);
        $this->gatewayJson('GET', $f['url'])->assertOk()->assertJsonPath('data', []);
        self::assertSame(0, DB::table('program_negative_pnl_configuration_revisions')->whereNull('ends_at')->count());
        self::assertSame(2, DB::table('program_negative_pnl_groups')->count());
    }

    public function test_half_open_history_and_frozen_rule_template_context(): void
    {
        $f = $this->fixture();
        $this->travel(2)->seconds();
        $first = $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk()->json('data');
        $port = app(ResolveNegativePnlProgramConfigurationPort::class);
        $resolve = fn (string $at) => $port->execute(new ResolveNegativePnlProgramConfigurationQueryData($f['program_id'], $f['module_id'], 'external-group', $at));
        self::assertNull($resolve(now('UTC')->subSecond()->toISOString()));
        self::assertSame($first['id'], $resolve($first['starts_at'])->id);
        $this->travel(2)->seconds();
        DB::table('rule_assignments')->where('id', $f['assignment_id'])->update(['ends_at' => now('UTC')]);
        $version = $this->version($f['plan_id'], $f['rule_id'], $f['binding_id']);
        $assignment = $this->assign($f['plan_id'], $f['rule_id'], $f['program_id'], $f['module_id'], $version);
        $payload = $f['payload'];
        $payload['groups'][0]['rule_version_id'] = $version;
        $second = $this->gatewayJson('PUT', $f['url'], $payload)->assertOk()->json('data');
        self::assertSame($second['id'], $resolve($second['starts_at'])->id);
        $old = $resolve(CarbonImmutable::parse($second['starts_at'])->subMicrosecond()->toISOString());
        self::assertSame($f['version_id'], $old->groups[0]->rule_version_id);
        self::assertSame($f['assignment_id'], $old->groups[0]->assignment_id);
        self::assertSame('0.10000000', $old->groups[0]->levels[0]->rate);
        self::assertSame($assignment, $resolve($second['starts_at'])->groups[0]->assignment_id);
        self::assertNull($port->execute(new ResolveNegativePnlProgramConfigurationQueryData($f['program_id'], $f['module_id'], 'missing', $first['starts_at'])));
        $this->travel(1)->seconds();
        $this->gatewayJson('PUT', $f['url'], ['cadence' => 'daily', 'groups' => []])->assertOk();
        self::assertNull($resolve(now('UTC')->toISOString()));
        self::assertSame($first['id'], $resolve($first['starts_at'])->id);
    }

    public function test_duplicate_groups_and_retroactive_inputs_are_rejected(): void
    {
        $f = $this->fixture();
        $this->gatewayJson('PUT', $f['url'], ['cadence' => 'daily', 'groups' => [$f['payload']['groups'][0], $f['payload']['groups'][0]]])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_NEGATIVE_PNL_CONFIGURATION');
        $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'starts_at' => '2020-01-01'])->assertUnprocessable();
        $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'cadence' => 'hourly'])->assertUnprocessable();
        self::assertSame(0, DB::table('program_negative_pnl_configuration_revisions')->count());
    }

    public function test_program_and_modules_must_belong_to_plan(): void
    {
        $f = $this->fixture();
        $other = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'other', 'name' => 'Other', 'module_ids' => [], 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        $this->gatewayJson('PUT', str_replace($f['plan_id'], $other, $f['url']), $f['payload'])->assertNotFound();
        $payload = $f['payload'];
        $payload['groups'][0]['module_id'] = (string) Str::uuid7();
        $this->gatewayJson('PUT', $f['url'], $payload)->assertUnprocessable();
    }

    public function test_draft_foreign_modality_and_absent_assignment_are_rejected(): void
    {
        $f = $this->fixture();
        DB::table('rule_versions')->where('id', $f['version_id'])->update(['status' => 'draft', 'published_at' => null]);
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_NEGATIVE_PNL_RULE_CONTEXT');
        DB::table('rule_versions')->where('id', $f['version_id'])->update(['status' => 'published', 'published_at' => now('UTC')]);
        DB::table('rules')->where('id', $f['rule_id'])->update(['strategy_type' => 'cpa_fixed_amount']);
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertUnprocessable();
        DB::table('rules')->where('id', $f['rule_id'])->update(['strategy_type' => 'negative_pnl_share']);
        DB::table('rule_assignments')->where('id', $f['assignment_id'])->update(['ends_at' => now('UTC')]);
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertUnprocessable();
    }

    public function test_foreign_template_binding_is_rejected_without_remote_calls(): void
    {
        $f = $this->fixture();
        $other = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'other', 'name' => 'Other', 'module_ids' => [], 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        DB::table('plan_payment_template_version_bindings')->where('id', $f['binding_id'])->update(['plan_id' => $other]);
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_NEGATIVE_PNL_CONFIGURATION');
        Http::assertNothingSent();
    }

    public function test_foreign_rule_and_incompatible_module_are_rejected_atomically(): void
    {
        $f = $this->fixture();
        $first = $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertOk()->json('data.id');
        $other = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'foreign', 'name' => 'Foreign', 'module_ids' => [], 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        DB::table('rules')->where('id', $f['rule_id'])->update(['plan_id' => $other]);
        $this->gatewayJson('PUT', $f['url'], [...$f['payload'], 'cadence' => 'daily'])->assertUnprocessable();
        $this->gatewayJson('GET', $f['url'])->assertOk()->assertJsonPath('data.id', $first);
        DB::table('rules')->where('id', $f['rule_id'])->update(['plan_id' => $f['plan_id']]);
        $modules = \Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->once()->andReturn([new ModuleSummaryData($f['module_id'], 'unsupported', 'Unsupported', true, 'active')]);
        $this->app->instance(ResolveModulesPort::class, $modules);
        $this->gatewayJson('PUT', $f['url'], $f['payload'])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_NEGATIVE_PNL_CONFIGURATION');
        self::assertSame(1, DB::table('program_negative_pnl_configuration_revisions')->count());
    }

    public function test_unknown_and_ambiguous_assignment_are_rejected_by_rules_owner(): void
    {
        $f = $this->fixture();
        $repository = \Mockery::mock(RuleAssignmentRepositoryInterface::class);
        $this->app->instance('rules.assignments.repositories.postgresql', $repository);
        $assignments = [];
        foreach ([1, 2] as $i) {
            $assignments[] = RuleAssignment::activate((string) Str::uuid7(), $f['rule_id'], $f['version_id'], $f['program_id'], $f['module_id'], now('UTC')->subDay()->toISOString());
        }
        $repository->shouldReceive('listEffectiveAt')->once()->andReturn($assignments);
        $this->expectException(InvalidNegativePnlRuleContextException::class);
        app(ResolveNegativePnlRuleContextPort::class)->execute(new ResolveNegativePnlRuleContextQueryData($f['plan_id'], $f['program_id'], $f['module_id'], $f['version_id'], now('UTC')->toISOString()));
    }

    /** @return array<string,mixed> */
    private function fixture(): array
    {
        $module = (string) ModuleRecord::query()->where('code', 'broker')->value('id');
        $plan = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', ['code' => 'pnl', 'name' => 'PnL', 'module_ids' => [$module], 'progression_period' => 'monthly'])->assertCreated()->json('data.id');
        $program = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/programs", ['code' => 'entry', 'name' => 'Entry', 'entry_threshold' => 0, 'module_ids' => [$module]])->assertCreated()->json('data.id');
        $template = $this->gatewayJson('POST', '/api/ib/v1/admin/payment-templates', ['name' => 'PnL rates'])->assertCreated()->json('data.id');
        $v = $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template}/versions", ['levels' => [['distribution_level' => 0, 'rate' => '0.1'], ['distribution_level' => 1, 'rate' => '0.05']]])->assertCreated()->json('data.versions.0');
        $this->gatewayJson('POST', "/api/ib/v1/admin/payment-templates/{$template}/versions/{$v['id']}/publish", ['lock_version' => $v['lock_version']])->assertOk();
        $binding = (string) Str::uuid7();
        DB::table('plan_payment_template_version_bindings')->insert(['id' => $binding, 'plan_id' => $plan, 'template_version_id' => $v['id'], 'created_at' => now('UTC')]);
        $rule = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules", ['name' => 'PnL share', 'strategy_type' => 'negative_pnl_share'])->assertCreated()->json('data.id');
        $version = $this->version($plan, $rule, $binding);
        $assignment = $this->assign($plan, $rule, $program, $module, $version);

        return ['plan_id' => $plan, 'program_id' => $program, 'module_id' => $module, 'rule_id' => $rule, 'version_id' => $version, 'binding_id' => $binding, 'assignment_id' => $assignment, 'url' => "/api/ib/v1/admin/plans/{$plan}/programs/{$program}/negative-pnl-configuration", 'payload' => ['cadence' => 'monthly', 'groups' => [['module_id' => $module, 'server_group_id' => 'external-group', 'rule_version_id' => $version]]]];
    }

    private function version(string $plan, string $rule, string $binding): string
    {
        $v = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules/{$rule}/versions", ['schema_version' => 1, 'configuration' => ['plan_payment_template_version_binding_id' => $binding]])->assertCreated()->json('data');

        return $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules/{$rule}/versions/{$v['id']}/publish", ['lock_version' => $v['lock_version']])->assertOk()->json('data.id');
    }

    private function assign(string $plan, string $rule, string $program, string $module, string $version): string
    {
        return $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan}/rules/{$rule}/assignments", ['program_id' => $program, 'module_id' => $module, 'rule_version_id' => $version])->assertCreated()->json('data.id');
    }
}
