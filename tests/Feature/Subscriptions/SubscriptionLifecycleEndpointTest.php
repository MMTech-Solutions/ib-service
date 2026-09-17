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

final class SubscriptionLifecycleEndpointTest extends TestCase
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

    public function test_cancel_ends_active_subscription_and_rejects_invalid_states(): void
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true);
        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $cancelled = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/cancel", [
            'lock_version' => $active['lock_version'],
            'reason' => 'Partner exit',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ended')
            ->assertJsonPath('data.current_placement', null)
            ->assertJsonPath('data.changes.1.action', 'cancel')
            ->assertJsonPath('data.changes.1.reason', 'Partner exit')
            ->assertJsonPath('data.changes.1.actor_external_user_id', $this->authorizedSub())
            ->json('data');

        self::assertNotNull($cancelled['closed_at']);
        self::assertSame(0, DB::table('subscription_placements')->whereNull('effective_until')->count());

        $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/current', [], $this->customerSub)
            ->assertNotFound();

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/cancel", [
            'lock_version' => $cancelled['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_ACTIVE');

        $pendingPlan = $this->createActivePlan(requiresApproval: true, withProgram: true, code: 'pending-cancel');
        $otherCustomer = $this->seedAuthorizedCustomer((string) Str::uuid7());
        $pending = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $pendingPlan['id'],
        ], $otherCustomer)->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$pending['id']}/cancel", [
            'lock_version' => $pending['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_NOT_ACTIVE');
    }

    public function test_change_plan_with_chosen_and_default_program_and_full_rollback(): void
    {
        $source = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'source');
        $destination = $this->createActivePlan(requiresApproval: true, withProgram: true, code: 'destination');
        $secondProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$destination['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
            'entry_threshold' => 100,
        ])->assertCreated()->json('data');

        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $source['id'],
        ], $this->customerSub)->assertCreated()
            ->assertJsonPath('data.current_placement.is_fixed', false)
            ->json('data');

        $changed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/change-plan", [
            'plan_id' => $destination['id'],
            'program_id' => $secondProgram['id'],
            'lock_version' => $active['lock_version'],
            'reason' => 'Upgrade ladder',
        ])->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan_id', $destination['id'])
            ->assertJsonPath('data.origin', 'admin_plan_change')
            ->assertJsonPath('data.requires_approval', null)
            ->assertJsonPath('data.replaces_subscription_id', $active['id'])
            ->assertJsonPath('data.current_placement.program_id', $secondProgram['id'])
            ->assertJsonPath('data.current_placement.is_fixed', false)
            ->assertJsonPath('data.changes.0.action', 'change_plan_in')
            ->json('data');

        self::assertNotSame($active['id'], $changed['id']);

        $ended = $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$active['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended')
            ->assertJsonPath('data.changes.1.action', 'change_plan_out')
            ->json('data');

        self::assertSame(
            $ended['changes'][1]['operation_id'],
            $changed['changes'][0]['operation_id'],
        );
        self::assertSame(1, DB::table('subscriptions')->whereIn('status', ['pending', 'active'])->count());
        self::assertSame(1, DB::table('subscriptions')->where('status', 'ended')->count());

        $this->customerGatewayJson('GET', '/api/ib/v1/customer/subscriptions/current', [], $this->customerSub)
            ->assertOk()
            ->assertJsonPath('data.id', $changed['id']);

        $defaultDest = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'default-dest');
        $defaulted = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$changed['id']}/change-plan", [
            'plan_id' => $defaultDest['id'],
            'lock_version' => $changed['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.plan_id', $defaultDest['id'])
            ->assertJsonPath('data.current_placement.program_id', $defaultDest['program_id'])
            ->json('data');

        $emptyPlan = $this->createActivePlan(requiresApproval: false, withProgram: false, code: 'empty-dest');
        $beforeCount = DB::table('subscriptions')->count();
        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$defaulted['id']}/change-plan", [
            'plan_id' => $emptyPlan['id'],
            'lock_version' => $defaulted['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PROGRAM_NOT_AVAILABLE');

        $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$defaulted['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan_id', $defaultDest['id']);
        self::assertSame($beforeCount, DB::table('subscriptions')->count());
    }

    public function test_change_plan_rejects_ineligible_destination_and_foreign_program(): void
    {
        $source = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'from');
        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $source['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $inactive = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'inactive-dest',
            'name' => 'Inactive destination',
            'module_ids' => [$this->brokerId()],
            'requires_approval' => false,
        ])->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/change-plan", [
            'plan_id' => $inactive['id'],
            'lock_version' => $active['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION');

        $archiveCandidate = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'archive-dest');
        $shown = $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$archiveCandidate['id']}")->assertOk()->json('data');
        $deactivated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$archiveCandidate['id']}/deactivate", [
            'reason' => 'Pause',
            'lock_version' => $shown['lock_version'],
        ])->assertOk()->json('data');
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$archiveCandidate['id']}", [
            'reason' => 'Retire',
            'lock_version' => $deactivated['lock_version'],
        ])->assertNoContent();

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/change-plan", [
            'plan_id' => $archiveCandidate['id'],
            'lock_version' => $active['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION');

        $other = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'other-dest');
        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/change-plan", [
            'plan_id' => $other['id'],
            'program_id' => $source['program_id'],
            'lock_version' => $active['lock_version'],
        ])->assertNotFound()->assertJsonPath('error.code', 'PROGRAM_NOT_FOUND');

        $this->gatewayJson('GET', "/api/ib/v1/admin/subscriptions/{$active['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan_id', $source['id'])
            ->assertJsonCount(1, 'data.changes');
    }

    public function test_change_program_preserves_placement_condition_and_lock_conflicts(): void
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'program-move');
        $secondProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
            'entry_threshold' => 50,
        ])->assertCreated()->json('data');

        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $unfixed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/placement/change", [
            'program_id' => $secondProgram['id'],
            'lock_version' => $active['lock_version'],
            'reason' => 'Move unfixed',
        ])->assertOk()
            ->assertJsonPath('data.id', $active['id'])
            ->assertJsonPath('data.current_placement.program_id', $secondProgram['id'])
            ->assertJsonPath('data.current_placement.is_fixed', false)
            ->assertJsonPath('data.changes.1.action', 'change_program')
            ->assertJsonPath('data.changes.1.previous_is_fixed', false)
            ->assertJsonPath('data.changes.1.next_is_fixed', false)
            ->json('data');

        $fixed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$unfixed['id']}/placement/fix", [
            'program_id' => $secondProgram['id'],
            'lock_version' => $unfixed['lock_version'],
            'reason' => 'Hold placement',
        ])->assertOk()
            ->assertJsonPath('data.current_placement.is_fixed', true)
            ->assertJsonPath('data.current_placement.program_id', $secondProgram['id'])
            ->assertJsonPath('data.changes.2.action', 'fix_placement')
            ->assertJsonPath('data.changes.2.reason', 'Hold placement')
            ->json('data');

        $movedFixed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$fixed['id']}/placement/change", [
            'program_id' => $plan['program_id'],
            'lock_version' => $fixed['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.current_placement.program_id', $plan['program_id'])
            ->assertJsonPath('data.current_placement.is_fixed', true)
            ->assertJsonPath('data.changes.3.previous_is_fixed', true)
            ->assertJsonPath('data.changes.3.next_is_fixed', true)
            ->json('data');

        $released = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$movedFixed['id']}/placement/release", [
            'lock_version' => $movedFixed['lock_version'],
            'reason' => 'Resume progression',
        ])->assertOk()
            ->assertJsonPath('data.current_placement.is_fixed', false)
            ->assertJsonPath('data.current_placement.program_id', $plan['program_id'])
            ->assertJsonPath('data.changes.4.action', 'release_placement')
            ->assertJsonPath('data.changes.4.previous_is_fixed', true)
            ->assertJsonPath('data.changes.4.next_is_fixed', false)
            ->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$released['id']}/placement/release", [
            'lock_version' => $released['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'SUBSCRIPTION_INVARIANT_VIOLATION');

        $refixed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$released['id']}/placement/fix", [
            'program_id' => $secondProgram['id'],
            'lock_version' => $released['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.current_placement.is_fixed', true)
            ->assertJsonPath('data.current_placement.program_id', $secondProgram['id'])
            ->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/placement/change", [
            'program_id' => $plan['program_id'],
            'lock_version' => $released['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_CONCURRENCY_CONFLICT');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/placement/fix", [
            'program_id' => $plan['program_id'],
            'lock_version' => $released['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_CONCURRENCY_CONFLICT');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/placement/release", [
            'lock_version' => $released['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_CONCURRENCY_CONFLICT');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/cancel", [
            'lock_version' => $released['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_CONCURRENCY_CONFLICT');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/change-plan", [
            'plan_id' => $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'lock-dest')['id'],
            'lock_version' => $released['lock_version'],
        ])->assertConflict()->assertJsonPath('error.code', 'SUBSCRIPTION_CONCURRENCY_CONFLICT');

        $foreign = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'foreign-program');
        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/placement/change", [
            'program_id' => $foreign['program_id'],
            'lock_version' => $refixed['lock_version'],
        ])->assertNotFound()->assertJsonPath('error.code', 'PROGRAM_NOT_FOUND');

        $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$refixed['id']}/placement/fix", [
            'program_id' => $foreign['program_id'],
            'lock_version' => $refixed['lock_version'],
        ])->assertNotFound()->assertJsonPath('error.code', 'PROGRAM_NOT_FOUND');
    }

    public function test_lifecycle_routes_require_admin_permission(): void
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'auth-life');
        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/cancel", [
            'lock_version' => $active['lock_version'],
        ]);
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/change-plan", [
            'plan_id' => $plan['id'],
            'lock_version' => $active['lock_version'],
        ]);
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/placement/change", [
            'program_id' => $plan['program_id'],
            'lock_version' => $active['lock_version'],
        ]);
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/placement/fix", [
            'program_id' => $plan['program_id'],
            'lock_version' => $active['lock_version'],
        ]);
        $this->assertGatewayAuthGuards('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/placement/release", [
            'lock_version' => $active['lock_version'],
        ]);
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
