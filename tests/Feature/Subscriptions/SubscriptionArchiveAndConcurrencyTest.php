<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Contracts\Ports\Input\LockPlanRowsPort;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\Support\InteractsWithCustomerGateway;
use Tests\TestCase;
use Throwable;

final class SubscriptionArchiveAndConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithAdminGateway;
    use InteractsWithCustomerGateway;

    private string $customerSub;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency races require PostgreSQL.');
        }

        $this->seedAuthorizedAdmin();
        $this->customerSub = $this->seedAuthorizedCustomer();
    }

    protected function tearDown(): void
    {
        DB::table('subscription_changes')->delete();
        DB::table('subscription_placements')->delete();
        DB::table('subscriptions')->delete();
        DB::table('program_module_selections')->delete();
        DB::table('programs')->delete();
        DB::table('plan_operational_changes')->delete();
        DB::table('plan_module_bindings')->delete();
        DB::table('plans')->delete();

        try {
            DB::disconnect('pgsql_racing');
        } catch (Throwable) {
        }

        parent::tearDown();
    }

    public function test_archive_rejects_open_pending_and_active_subscriptions(): void
    {
        $pendingPlan = $this->createInactivePlanWithOpenPending();
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$pendingPlan['id']}", [
            'lock_version' => $pendingPlan['lock_version'],
            'reason' => 'Still open pending',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_HAS_OPEN_SUBSCRIPTIONS');

        $activePlan = $this->createInactivePlanWithOpenActive();
        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$activePlan['id']}", [
            'lock_version' => $activePlan['lock_version'],
            'reason' => 'Still open active',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_HAS_OPEN_SUBSCRIPTIONS');
    }

    public function test_archive_sees_open_subscription_created_while_plan_lock_is_held(): void
    {
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true, code: 'race-apply');
        $inactive = $this->deactivatePlan($plan['id'], $plan['lock_version']);

        $racing = $this->racingConnection();
        $racing->beginTransaction();

        try {
            $racing->table('plans')->where('id', $plan['id'])->lockForUpdate()->first();
            $racing->table('subscriptions')->insert([
                'id' => (string) Str::uuid7(),
                'external_user_id' => (string) Str::uuid7(),
                'plan_id' => $plan['id'],
                'status' => 'pending',
                'origin' => 'user_application',
                'requires_approval' => true,
                'replaces_subscription_id' => null,
                'activated_at' => null,
                'closed_at' => null,
                'lock_version' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            $this->assertLockTimesOut(fn () => app(LockPlanRowsPort::class)->lockAscending([$plan['id']]));
            $racing->commit();
        } catch (Throwable $exception) {
            $racing->rollBack();
            throw $exception;
        }

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$inactive['id']}", [
            'lock_version' => $inactive['lock_version'],
            'reason' => 'Open pending appeared under lock',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_HAS_OPEN_SUBSCRIPTIONS');
    }

    public function test_change_plan_destination_lock_times_out_when_held_elsewhere(): void
    {
        $source = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'race-src');
        $destination = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'race-dst');
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $source['id'],
        ], $this->customerSub)->assertCreated();

        $racing = $this->racingConnection();
        $racing->beginTransaction();

        try {
            $racing->table('plans')->where('id', $destination['id'])->lockForUpdate()->first();
            $this->assertLockTimesOut(fn () => app(LockPlanRowsPort::class)->lockAscending([$destination['id']]));
        } finally {
            $racing->rollBack();
        }
    }

    public function test_archived_plan_rejects_apply_and_leaves_no_open_subscription(): void
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'race-archive');
        $deactivated = $this->deactivatePlan($plan['id'], $plan['lock_version']);

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$plan['id']}", [
            'lock_version' => $deactivated['lock_version'],
            'reason' => 'Archive empty plan',
        ])->assertNoContent();

        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertUnprocessable()
            ->assertJsonPath('error.code', 'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION');

        self::assertSame(0, DB::table('subscriptions')->where('plan_id', $plan['id'])->count());
    }

    public function test_placement_mutations_leave_contiguous_non_overlapping_history(): void
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'race-place');
        $secondProgram = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$plan['id']}/programs", [
            'code' => 'advanced',
            'name' => 'Advanced',
            'entry_threshold' => 50,
        ])->assertCreated()->json('data');

        $active = $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->customerSub)->assertCreated()->json('data');

        $racing = $this->racingConnection();
        $racing->beginTransaction();

        try {
            $racing->table('subscriptions')->where('id', $active['id'])->lockForUpdate()->first();
            $this->assertLockTimesOut(function () use ($active): void {
                DB::transaction(function () use ($active): void {
                    DB::table('subscriptions')->where('id', $active['id'])->lockForUpdate()->first();
                });
            });
        } finally {
            $racing->rollBack();
        }

        $fixed = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$active['id']}/placement/fix", [
            'program_id' => $secondProgram['id'],
            'lock_version' => $active['lock_version'],
        ])->assertOk()->json('data');

        $moved = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$fixed['id']}/placement/change", [
            'program_id' => $plan['program_id'],
            'lock_version' => $fixed['lock_version'],
        ])->assertOk()->json('data');

        $released = $this->gatewayJson('POST', "/api/ib/v1/admin/subscriptions/{$moved['id']}/placement/release", [
            'lock_version' => $moved['lock_version'],
        ])->assertOk()->json('data');

        $placements = DB::table('subscription_placements')
            ->where('subscription_id', $released['id'])
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get();

        self::assertCount(4, $placements);
        self::assertNull($placements[3]->effective_until);
        self::assertSame($placements[0]->effective_until, $placements[1]->effective_from);
        self::assertSame($placements[1]->effective_until, $placements[2]->effective_from);
        self::assertSame($placements[2]->effective_until, $placements[3]->effective_from);
        self::assertSame(1, $placements->whereNull('effective_until')->count());
    }

    private function assertLockTimesOut(callable $callback): void
    {
        DB::statement("SET lock_timeout TO '500ms'");

        try {
            $callback();
            self::fail('Expected a PostgreSQL lock timeout.');
        } catch (Throwable $exception) {
            $this->assertMatchesRegularExpression('/lock|timeout|canceling statement/i', $exception->getMessage());
        } finally {
            try {
                DB::rollBack();
            } catch (Throwable) {
            }
            DB::statement('SET lock_timeout TO 0');
        }
    }

    private function racingConnection(): Connection
    {
        config([
            'database.connections.pgsql_racing' => config('database.connections.pgsql_testing'),
        ]);

        return DB::connection('pgsql_racing');
    }

    /**
     * @return array{id: string, lock_version: int}
     */
    private function createInactivePlanWithOpenPending(): array
    {
        $plan = $this->createActivePlan(requiresApproval: true, withProgram: true, code: 'open-pending');
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->seedAuthorizedCustomer((string) Str::uuid7()))->assertCreated();

        return $this->deactivatePlan($plan['id'], $plan['lock_version']);
    }

    /**
     * @return array{id: string, lock_version: int}
     */
    private function createInactivePlanWithOpenActive(): array
    {
        $plan = $this->createActivePlan(requiresApproval: false, withProgram: true, code: 'open-active');
        $this->customerGatewayJson('POST', '/api/ib/v1/customer/subscriptions', [
            'plan_id' => $plan['id'],
        ], $this->seedAuthorizedCustomer((string) Str::uuid7()))->assertCreated();

        return $this->deactivatePlan($plan['id'], $plan['lock_version']);
    }

    /**
     * @return array{id: string, lock_version: int}
     */
    private function deactivatePlan(string $planId, int $lockVersion): array
    {
        $deactivated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$planId}/deactivate", [
            'lock_version' => $lockVersion,
            'reason' => 'Prepare archive',
        ])->assertOk()->json('data');

        return [
            'id' => $deactivated['id'],
            'lock_version' => $deactivated['lock_version'],
        ];
    }

    /**
     * @return array{id: string, program_id: ?string, lock_version: int}
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
            'lock_version' => $created['lock_version'],
            'reason' => 'Launch',
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
            'lock_version' => $activated['lock_version'],
        ];
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
