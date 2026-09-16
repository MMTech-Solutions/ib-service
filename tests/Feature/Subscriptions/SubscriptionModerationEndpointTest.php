<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;

final class SubscriptionModerationEndpointTest extends TestCase
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

    public function test_automatic_application_activates_with_first_program(): void
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true);

        $created = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.requires_approval', false)
            ->assertJsonPath('data.external_user_id', $this->customerSub)
            ->assertJsonPath('data.current_placement.program_id', $plan['program_id'])
            ->assertJsonPath('data.current_placement.is_fixed', false)
            ->json('data');

        $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/current', [], $this->customerSub)
            ->assertOk()
            ->assertJsonPath('data.id', $created['id'])
            ->assertJsonPath('data.status', 'active');

        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_OPEN_CONFLICT');
    }

    public function test_pending_application_approval_and_rejection_flows(): void
    {
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true);
        $secondProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
            'entry_threshold' => 100,
        ])->assertCreated()->json('data');

        $pending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.current_placement', null)
            ->json('data');

        $this->gatewayJson('GET', '/api/ib/v1/admin/subscriptions?status=pending&plan_id='.$plan['id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $pending['id'])
            ->assertJsonPath('meta.filters.status', 'pending');

        $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$pending['id']}")
            ->assertOk()
            ->assertJsonPath('data.changes.0.action', 'request')
            ->assertJsonPath('data.changes.0.next_status', 'pending');

        $otherPlan = $this->createActivePlan(requiresApproval: true, withProgram: true, code: 'other');
        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$pending['id']}/approve", [
            'program_id' => $otherPlan['program_id'],
        ])->assertNotFound()->assertJsonPath('error.code', 'PROGRAM_NOT_FOUND');

        $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$pending['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.current_placement', null);

        $approved = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$pending['id']}/approve", [
            'program_id' => $secondProgram['id'],
            'reason' => 'Qualified partner',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.requires_approval', true)
            ->assertJsonPath('data.current_placement.program_id', $secondProgram['id'])
            ->assertJsonPath('data.changes.1.action', 'approve')
            ->json('data');

        $this->assertSame($pending['id'], $approved['id']);

        $otherCustomer = $this->seedAuthorizedCustomer((string) Str::uuid7());
        $otherPending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $otherCustomer)->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$otherPending['id']}/reject", [])
            ->assertUnprocessable();

        $rejected = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$otherPending['id']}/reject", [
            'reason' => 'Incomplete profile',
        ])->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.changes.1.action', 'reject')
            ->assertJsonPath('data.changes.1.reason', 'Incomplete profile')
            ->json('data');

        $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/current', [], $otherCustomer)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_FOUND');

        $reapplied = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $otherCustomer)->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        self::assertNotSame($rejected['id'], $reapplied['id']);
    }

    public function test_default_program_approval_and_ineligible_plan_rollbacks(): void
    {
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true);
        $pending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$pending['id']}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.current_placement.program_id', $plan['program_id']);

        $secondCustomer = $this->seedAuthorizedCustomer((string) Str::uuid7());
        $secondPending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $secondCustomer)->assertCreated()->json('data');

        $shown = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$plan['id']}")->assertOk()->json('data');
        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/deactivate", [
            'reason' => 'Pause',
            'lock_version' => $shown['lock_version'],
        ])->assertOk();

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$secondPending['id']}/approve", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION');

        $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$secondPending['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(1, 'data.changes');
    }

    public function test_inactive_or_archived_plans_and_missing_program_leave_no_effects(): void
    {
        $inactive = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'inactive-plan',
            'name' => 'Inactive',
            'module_ids' => [$this->brokerId()],
            'requires_approval' => false,
        ])->assertCreated()->json('data');

        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $inactive['id'],
        ], $this->customerSub)->assertUnprocessable()
            ->assertJsonPath('error.code', 'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION');

        $activeNoProgram = $this->createActivePlan(requiresApproval: false, withProgram: false, code: 'empty-ladder');
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $activeNoProgram['id'],
        ], $this->customerSub)->assertUnprocessable()
            ->assertJsonPath('error.code', 'PROGRAM_NOT_AVAILABLE');

        self::assertSame(0, DB::table('subscriptions')->count());

        $approvalPlan = $this->createActivePlan(requiresApproval: true, withProgram: false, code: 'pending-empty');
        $pending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $approvalPlan['id'],
        ], $this->customerSub)->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$pending['id']}/approve", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PROGRAM_NOT_AVAILABLE');

        $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$pending['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $shown = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$approvalPlan['id']}")->assertOk()->json('data');
        $deactivated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$approvalPlan['id']}/deactivate", [
            'reason' => 'Prepare archive',
            'lock_version' => $shown['lock_version'],
        ])->assertOk()->json('data');
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$approvalPlan['id']}", [
            'reason' => 'Retired',
            'lock_version' => $deactivated['lock_version'],
        ])->assertNoContent();

        $anotherCustomer = $this->seedAuthorizedCustomer((string) Str::uuid7());
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $approvalPlan['id'],
        ], $anotherCustomer)->assertUnprocessable()
            ->assertJsonPath('error.code', 'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION');
    }

    public function test_audience_isolation_and_permission_guards(): void
    {
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true);
        $created = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $otherCustomer = $this->seedAuthorizedCustomer((string) Str::uuid7());
        $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/current', [], $otherCustomer)
            ->assertNotFound();

        $this->customerGatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$created['id']}", [], $this->customerSub)
            ->assertForbidden();

        $this->gatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ])->assertForbidden();

        $this->assertCustomerGatewayAuthGuards('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ]);
        $this->assertCustomerGatewayAuthGuards('GET', '/api/ib/v1/customer/subscriptions/current');
        $this->assertGatewayAuthGuards('GET', '/api/ib/v1/admin/subscriptions');
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/subscriptions/{$created['id']}/approve");
    }

    public function test_second_application_for_same_user_is_rejected_under_open_guard(): void
    {
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true);

        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated();

        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertConflict()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_OPEN_CONFLICT');

        self::assertSame(1, DB::table('subscriptions')->where('external_user_id', $this->customerSub)->count());
    }

    /**
     * @return array{id: string, program_id: ?string}
     */
    private function createActivePlan(bool $requiresApproval, bool $withProgram, string $code = 'mix'): array
    {
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => $code,
            'name' => ucfirst($code),
            'module_ids' => [$this->brokerId()],
            'requires_approval' => $requiresApproval,
        ])->assertCreated()->json('data');

        $activated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Ready',
            'lock_version' => $created['lock_version'],
        ])->assertOk()->json('data');

        $programId = null;
        if ($withProgram) {
            $programId = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$activated['id']}/programs", [
                'code' => 'basic',
                'name' => 'Basic',
                'entry_threshold' => 0,
            ])->assertCreated()->json('data.id');
        }

        return [
            'id' => $activated['id'],
            'program_id' => $programId,
        ];
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
