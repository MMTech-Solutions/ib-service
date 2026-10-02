<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class SubscriptionRewardRatesEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;
    use RefreshDatabase;

    private string $customerSub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
        $this->customerSub = $this->seedAuthorizedCustomer();
    }

    public function test_admin_updates_open_subscription_reward_rates_with_an_audit_snapshot(): void
    {
        $subscription = $this->createActiveSubscription();

        $updated = $this->gatewayJson('PATCH', "/api/ib/v1/admin/subscriptions/{$subscription['id']}/reward-rates", [
            'personal_rate' => '0.75',
            'is_master' => true,
            'master_rate' => '1.25',
            'lock_version' => $subscription['lock_version'],
            'reason' => 'Master agreement',
        ])->assertOk()
            ->assertJsonPath('data.personal_rate', '0.75000000')
            ->assertJsonPath('data.is_master', true)
            ->assertJsonPath('data.master_rate', '1.25000000')
            ->assertJsonPath('data.changes.1.action', 'update_reward_rates')
            ->assertJsonPath('data.changes.1.previous_personal_rate', '1.00000000')
            ->assertJsonPath('data.changes.1.previous_is_master', false)
            ->assertJsonPath('data.changes.1.previous_master_rate', '1.00000000')
            ->assertJsonPath('data.changes.1.next_personal_rate', '0.75000000')
            ->assertJsonPath('data.changes.1.next_is_master', true)
            ->assertJsonPath('data.changes.1.next_master_rate', '1.25000000')
            ->json('data');

        $unchanged = $this->gatewayJson('PATCH', "/api/ib/v1/admin/subscriptions/{$updated['id']}/reward-rates", [
            'personal_rate' => '0.75',
            'is_master' => true,
            'master_rate' => '1.25',
            'lock_version' => $updated['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.lock_version', $updated['lock_version'])
            ->assertJsonCount(2, 'data.changes')
            ->json('data');

        $changed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$unchanged['id']}/change-plan", [
            'plan_id' => $this->createActivePlan('destination')['id'],
            'lock_version' => $unchanged['lock_version'],
            'reason' => 'Move agreement',
        ])->assertOk()
            ->assertJsonPath('data.personal_rate', '0.75000000')
            ->assertJsonPath('data.is_master', true)
            ->assertJsonPath('data.master_rate', '1.25000000')
            ->json('data');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/subscriptions/{$subscription['id']}/reward-rates", [
            'personal_rate' => '0.50',
            'is_master' => false,
            'master_rate' => '1',
            'lock_version' => $unchanged['lock_version'] + 1,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'SUBSCRIPTION_INVARIANT_VIOLATION');

        self::assertNotSame($subscription['id'], $changed['id']);
    }

    public function test_reward_rates_validate_ranges_and_require_administrative_access(): void
    {
        $subscription = $this->createActiveSubscription();

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/subscriptions/{$subscription['id']}/reward-rates", [
            'personal_rate' => '1.01',
            'is_master' => true,
            'master_rate' => '0.99',
            'lock_version' => $subscription['lock_version'],
        ])->assertUnprocessable();

        $this->assertGatewayAuthGuards('PATCH', "/api/ib/v1/admin/subscriptions/{$subscription['id']}/reward-rates", [
            'personal_rate' => '1',
            'is_master' => false,
            'master_rate' => '1',
            'lock_version' => $subscription['lock_version'],
        ]);
    }

    /** @return array{id: string, lock_version: int} */
    private function createActiveSubscription(): array
    {
        $plan = $this->createActivePlan('source');

        return $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');
    }

    /** @return array{id: string} */
    private function createActivePlan(string $code): array
    {
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => $code.'-'.Str::lower(Str::random(4)),
            'name' => ucfirst($code),
            'module_ids' => [(string) ModuleRecord::query()->where('code', 'broker')->value('id')],
            'progression_period' => 'monthly',
            'requires_approval' => false,
        ])->assertCreated()->json('data');
        $active = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'lock_version' => $created['lock_version'],
            'reason' => 'Ready',
        ])->assertOk()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$active['id']}/programs", [
            'code' => 'basic',
            'name' => 'Basic',
            'entry_threshold' => 0,
        ])->assertCreated();

        return ['id' => $active['id']];
    }
}
