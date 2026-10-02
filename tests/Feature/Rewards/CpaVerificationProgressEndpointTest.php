<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

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

final class CpaVerificationProgressEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private string $customerContextId;

    private string $otherContextId;

    private string $programId;

    private string $moduleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->seedFixtures();
    }

    public function test_customer_only_lists_its_own_progress_without_administrative_fields(): void
    {
        $this->seedAuthorizedCustomer();

        $response = $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/cpa-progress?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->customerContextId)
            ->assertJsonPath('data.0.currency_precision', 2)
            ->assertJsonPath('meta.filters.ib_user_id', LocalRbacSnapshotSeeder::CUSTOMER_SUB)
            ->assertJsonPath('meta.filters.status', 'pending')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonMissingPath('data.0.ib_user_id')
            ->assertJsonMissingPath('data.0.reward_id')
            ->assertJsonMissingPath('data.0.last_error_code')
            ->assertJsonMissingPath('meta.pagination.links');

        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/cpa-progress?ib_user_id='.Str::uuid7())
            ->assertUnprocessable();
    }

    public function test_administrator_can_filter_and_receives_audit_fields(): void
    {
        $response = $this->gatewayJson('GET', '/api/ib/v1/admin/rewards/cpa-progress?'.http_build_query([
            'ib_user_id' => LocalRbacSnapshotSeeder::CUSTOMER_SUB,
            'program_id' => $this->programId,
            'module_id' => $this->moduleId,
            'status' => 'pending',
            'per_page' => 1,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->customerContextId)
            ->assertJsonPath('data.0.ib_user_id', LocalRbacSnapshotSeeder::CUSTOMER_SUB)
            ->assertJsonPath('data.0.last_error_code', 'evidence_unavailable')
            ->assertJsonPath('data.0.reward_id', null)
            ->assertJsonPath('meta.filters.program_id', $this->programId)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonMissingPath('meta.pagination.links');
    }

    public function test_admin_permission_and_gateway_guards_are_required(): void
    {
        $this->assertGatewayAuthGuards('GET', '/api/ib/v1/admin/rewards/cpa-progress');

        $this->seedAuthorizedCustomer();
        $this->customerGatewayJson('GET', '/api/ib/v1/admin/rewards/cpa-progress')->assertForbidden();
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/rewards/cpa-progress?per_page=101')->assertUnprocessable();
    }

    private function seedFixtures(): void
    {
        $now = now('UTC');
        $plan = PlanRecord::factory()->create();
        $program = ProgramRecord::factory()->create(['plan_id' => $plan->id, 'position' => 1, 'entry_threshold' => 0]);
        $module = ModuleRecord::query()->where('code', 'broker')->firstOrFail();
        $rule = RuleRecord::query()->create([
            'id' => (string) Str::uuid7(), 'plan_id' => $plan->id, 'name' => 'CPA Test Rule', 'slug' => 'cpa-test-rule',
            'description' => null, 'strategy_type' => RuleStrategyType::CpaFixedAmount->value, 'lock_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $version = RuleVersionRecord::query()->create([
            'id' => (string) Str::uuid7(), 'rule_id' => $rule->id, 'version_number' => 1,
            'status' => RuleVersionStatus::Published->value, 'schema_version' => 1,
            'configuration' => ['currency_precision' => 2], 'published_at' => $now, 'lock_version' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->programId = (string) $program->id;
        $this->moduleId = (string) $module->id;
        $this->customerContextId = $this->insertProgress(
            LocalRbacSnapshotSeeder::CUSTOMER_SUB,
            '11111111-1111-4111-8111-111111111111',
            $plan->id,
            $program->id,
            $module->id,
            $rule->id,
            $version->id,
            'pending',
            'evidence_unavailable',
            now('UTC')->subMinute(),
        );
        $this->otherContextId = $this->insertProgress(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1',
            '22222222-2222-4222-8222-222222222222',
            $plan->id,
            $program->id,
            $module->id,
            $rule->id,
            $version->id,
            'qualified',
            null,
            now('UTC'),
        );
    }

    private function insertProgress(string $ibUserId, string $referredUserId, string $planId, string $programId, string $moduleId, string $ruleId, string $ruleVersionId, string $status, ?string $error, mixed $capturedAt): string
    {
        $id = (string) Str::uuid7();
        $now = now('UTC');
        DB::table('cpa_contexts')->insert([
            'id' => $id, 'referred_user_id' => $referredUserId, 'ib_user_id' => $ibUserId,
            'plan_id' => $planId, 'program_id' => $programId, 'module_id' => $moduleId,
            'rule_assignment_id' => null, 'rule_id' => $ruleId, 'rule_version_id' => $ruleVersionId,
            'symbols_snapshot' => '[]', 'requirements_snapshot' => json_encode(['currency_precision' => 2], JSON_THROW_ON_ERROR),
            'captured_at' => $capturedAt, 'reward_id' => null,
        ]);
        DB::table('cpa_verification_progress')->insert([
            'id' => (string) Str::uuid7(), 'cpa_context_id' => $id, 'referred_user_id' => $referredUserId,
            'ib_user_id' => $ibUserId, 'status' => $status, 'observed_volume' => '10.50000000',
            'required_volume' => '20.00000000', 'volume_unit_code' => 'lot', 'observed_deposit_minor' => 1000,
            'required_deposit_minor' => 2000, 'currency_code' => 'USD', 'volume_satisfied' => false,
            'deposit_satisfied' => false, 'observed_from' => $capturedAt, 'observed_until' => null,
            'last_evaluated_at' => $capturedAt, 'last_error_code' => $error, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $id;
    }
}
