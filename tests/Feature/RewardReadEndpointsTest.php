<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Enums\RuleVersionStatus;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleRecord;
use App\Features\Rules\Catalog\Repositories\PostgreSql\Models\RuleVersionRecord;
use Database\Seeders\LocalRbacSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class RewardReadEndpointsTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->seedAuthorizedCustomer();
    }

    public function test_customer_reads_only_its_own_rewards_without_audit_or_evidence(): void
    {
        $own = $this->seedReward('pending');
        DB::table('rewards')->where('id', $own)->update(['beneficiary_user_id' => LocalRbacSnapshotSeeder::CUSTOMER_SUB]);
        $other = $this->seedReward('pending');
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own)->assertJsonPath('meta.pagination.total', 1)->assertJsonMissingPath('data.0.audit');
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/'.$own)->assertOk()->assertJsonPath('data.id', $own)->assertJsonMissingPath('data.audit')->assertJsonMissingPath('data.beneficiary_id');
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/'.$other)->assertNotFound();
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards?beneficiary_id='.$other)->assertUnprocessable();
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards?per_page=101')->assertUnprocessable();
    }

    public function test_administration_filters_paginates_and_receives_frozen_audit(): void
    {
        $id = $this->seedReward('pending');
        $this->seedReward('settled');
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 2)->assertJsonMissingPath('meta.pagination.links');
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards?status=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.audit.beneficiary_id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/'.$id)->assertOk()->assertJsonPath('data.audit.evidence', [])->assertJsonMissingPath('data.audit.settlement_lock_token');
    }

    public function test_date_filters_accept_single_bound_and_use_half_open_utc_interval(): void
    {
        $id = $this->seedReward('pending');
        DB::table('rewards')->where('id', $id)->update(['created_at' => '2026-10-01T12:00:00Z']);
        $url = '/api/ib/v1/admin/rewards?';
        $this->gatewayJson('GET', $url.http_build_query(['occurred_until' => '2026-10-01T12:00:00Z']))->assertOk()->assertJsonCount(0, 'data');
        $this->gatewayJson('GET', $url.http_build_query(['occurred_from' => '2026-10-01T08:00:00-04:00', 'occurred_until' => '2026-10-01T12:00:01Z']))->assertOk()->assertJsonCount(1, 'data');
        $this->gatewayJson('GET', $url.http_build_query(['occurred_from' => '2026-10-02T00:00:00Z', 'occurred_until' => '2026-10-01T00:00:00Z']))->assertUnprocessable();
    }

    public function test_jobs_and_periods_are_administrative_and_filter_frozen_context(): void
    {
        $reward = $this->seedReward('pending');
        $row = DB::table('rewards')->where('id', $reward)->first();
        $job = (string) Str::uuid7();
        $period = (string) Str::uuid7();
        DB::table('negative_pnl_jobs')->insert(['id' => $job, 'identity_key' => hash('sha256', $job), 'subscription_id' => (string) Str::uuid7(), 'beneficiary_id' => $row->beneficiary_user_id, 'plan_id' => $row->plan_id, 'module_id' => $row->module_id, 'server_group_id' => 'group', 'cadence' => 'daily', 'next_cut_at' => now('UTC')]);
        DB::table('negative_pnl_periods')->insert(['id' => $period, 'job_id' => $job, 'occurred_until' => now('UTC'), 'status' => 'ready', 'inputs' => json_encode(['subscription' => ['program_id' => $row->program_id]]), 'receipts' => '{}']);
        DB::table('rewards')->where('id', $reward)->update(['summary_snapshot' => json_encode(['period_id' => $period])]);
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/negative-pnl/jobs?program_id='.$row->program_id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/negative-pnl/jobs/'.$job)->assertOk()->assertJsonPath('data.id', $job)->assertJsonMissingPath('data.lease_token');
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/negative-pnl/periods?job_id='.$job)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reward_ids', [$reward]);
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/negative-pnl/periods/'.$period)->assertOk()->assertJsonPath('data.inputs.subscription.program_id', $row->program_id);
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/negative-pnl/jobs')->assertNotFound();
    }

    public function test_read_routes_require_gateway_and_administrative_permission(): void
    {
        $this->assertGatewayAuthGuards('GET', '/api/ib/v1/admin/rewards');
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards', [], (string) Str::uuid7())->assertForbidden();
        $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/negative-pnl/jobs', [], (string) Str::uuid7())->assertForbidden();
    }

    private function seedReward(string $status): string
    {
        config()->set('finance.base_url', 'http://finance.test');
        $now = now('UTC');
        $plan = PlanRecord::factory()->create(['is_active' => true]);
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $module = ModuleRecord::query()->where('code', 'broker')->first() ?? ModuleRecord::factory()->create(['code' => 'broker']);
        $rule = RuleRecord::query()->create(['id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'Reward correction '.Str::random(), 'slug' => 'reward-correction-'.Str::lower(Str::random()), 'strategy_type' => RuleStrategyType::CpaFixedAmount->value, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $version = RuleVersionRecord::query()->create(['id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1, 'status' => RuleVersionStatus::Published->value, 'schema_version' => 1, 'configuration' => ['currency_precision' => 2], 'published_at' => $now, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $assignment = (string) Str::uuid7();
        DB::table('rule_assignments')->insert(['id' => $assignment, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'program_id' => $program->id, 'module_id' => $module->id, 'scope_type' => 'all', 'starts_at' => $now, 'lock_version' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $rewardId = (string) Str::uuid7();
        DB::table('rewards')->insert(['id' => $rewardId, 'beneficiary_user_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'plan_id' => $plan->id, 'program_id' => $program->id, 'module_id' => $module->id, 'rule_assignment_id' => $assignment, 'rule_id' => $rule->id, 'rule_version_id' => $version->id, 'amount_minor' => 2500, 'currency_code' => 'USD', 'currency_precision' => 2, 'status' => $status, 'summary_snapshot' => '{}', 'settlement_idempotency_key' => 'ib-service:reward:'.$rewardId.':settlement', 'settlement_reference_id' => $status === 'settled' ? '781' : null, 'created_at' => $now, 'updated_at' => $now]);

        return $rewardId;
    }
}
