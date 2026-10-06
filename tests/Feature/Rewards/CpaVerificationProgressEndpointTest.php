<?php

declare(strict_types=1);

namespace Tests\Feature\Rewards;

use Database\Seeders\LocalRbacSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CpaFixtures;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class CpaVerificationProgressEndpointTest extends TestCase
{
    use CpaFixtures;
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
            ->assertJsonPath('data.0.deposit_currency_precision', 2)
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
        $customer = $this->cpaFixture(LocalRbacSnapshotSeeder::CUSTOMER_SUB, false);
        $other = $this->cpaFixture('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1', false);
        $this->customerContextId = $customer['context']->id;
        $this->otherContextId = $other['context']->id;
        $this->programId = $customer['program']->id;
        $this->moduleId = $customer['modules'][0]->id;
        DB::table('cpa_verification_progress')->where('cpa_context_id', $this->customerContextId)->update(['last_error_code' => 'evidence_unavailable']);
        DB::table('cpa_verification_progress')->where('cpa_context_id', $this->otherContextId)->update(['status' => 'qualified']);
    }
}
