<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class PlanCatalogEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_operator_can_manage_the_plan_catalog_lifecycle(): void
    {
        $brokerId = $this->brokerId();

        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
            'description' => 'Mixed activity',
        ])->assertCreated()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.modules', [])
            ->json('data');

        $this->gatewayJson('GET', '/api/ib/v1/admin/plans?search=mix')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'mix');

        $updated = $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$created['id']}", [
            'module_ids' => [$brokerId],
            'lock_version' => $created['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.modules.0.module_id', $brokerId)
            ->json('data');

        $activated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Ready for use',
            'lock_version' => $updated['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->json('data');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$created['id']}", [
            'module_ids' => [],
            'lock_version' => $activated['lock_version'],
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'PLAN_BINDINGS_REQUIRED');

        $deactivated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/deactivate", [
            'reason' => 'Pause offering',
            'lock_version' => $activated['lock_version'],
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->json('data');

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$created['id']}", [
            'reason' => 'Retired',
            'lock_version' => $deactivated['lock_version'],
        ])->assertNoContent();

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$created['id']}")
            ->assertNotFound();
        $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix again',
        ])->assertConflict()->assertJsonPath('error.code', 'PLAN_CODE_CONFLICT');
        $this->assertDatabaseHas('plan_module_bindings', [
            'plan_id' => $created['id'],
            'module_id' => $brokerId,
        ]);
    }

    public function test_new_bindings_reject_unknown_or_inactive_modules_but_existing_inactive_bindings_remain(): void
    {
        $brokerId = $this->brokerId();
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'broker-plan',
            'name' => 'Broker',
            'module_ids' => [$brokerId],
        ])->assertCreated()->json('data');

        $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'invalid-plan',
            'name' => 'Invalid',
            'module_ids' => ['01993ac2-8750-73fd-b102-ba24fb06d8be'],
        ])->assertNotFound()->assertJsonPath('error.code', 'MODULE_NOT_FOUND');

        DB::table('modules')->where('id', $brokerId)->update(['is_active' => false, 'lock_version' => 2]);
        $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'inactive-bind',
            'name' => 'Inactive bind',
            'module_ids' => [$brokerId],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'MODULE_INACTIVE');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$created['id']}", [
            'name' => 'Broker kept',
            'lock_version' => $created['lock_version'],
        ])->assertOk()->assertJsonPath('data.modules.0.module_id', $brokerId)
            ->assertJsonPath('data.modules.0.is_active', false);
    }

    public function test_deactivating_the_last_operational_module_deactivates_the_plan_and_does_not_reactivate_it(): void
    {
        $brokerId = $this->brokerId();
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'broker-plan',
            'name' => 'Broker',
            'module_ids' => [$brokerId],
        ])->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Launch',
            'lock_version' => $created['lock_version'],
        ])->assertOk()->assertJsonPath('data.is_active', true);

        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$brokerId}/deactivate", [
            'reason' => 'Panic button',
            'lock_version' => 1,
        ])->assertOk();

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('plan_operational_changes', [
            'plan_id' => $created['id'],
            'action' => 'deactivate',
            'actor_kind' => 'system',
            'cause_module_id' => $brokerId,
        ]);

        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$brokerId}/activate", [
            'reason' => 'Recovered',
            'lock_version' => 2,
        ])->assertOk();

        $this->gatewayJson('GET', "/api/ib/v1/admin/plans/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_plan_routes_enforce_gateway_identity_and_permission(): void
    {
        $this->getJson('/api/ib/v1/admin/plans')->assertUnauthorized();
        $this->assertGatewayAuthGuards('POST', '/api/ib/v1/admin/plans', [
            'code' => 'mix',
            'name' => 'Mix',
        ]);
    }

    public function test_activate_without_modules_is_rejected_and_archive_requires_inactive_plan(): void
    {
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'empty',
            'name' => 'Empty',
        ])->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Too soon',
            'lock_version' => $created['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_CANNOT_ACTIVATE');

        $brokerId = $this->brokerId();
        $updated = $this->gatewayJson('PATCH', "/api/ib/v1/admin/plans/{$created['id']}", [
            'module_ids' => [$brokerId],
            'lock_version' => $created['lock_version'],
        ])->assertOk()->json('data');

        $activated = $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Launch',
            'lock_version' => $updated['lock_version'],
        ])->assertOk()->json('data');

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$created['id']}", [
            'reason' => 'Still active',
            'lock_version' => $activated['lock_version'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'PLAN_CANNOT_ARCHIVE');
    }

    public function test_paused_modules_remain_selectable(): void
    {
        $brokerId = $this->brokerId();
        $this->gatewayJson('POST', "/api/ib/v1/admin/modules/{$brokerId}/pause", [
            'reason' => 'Risk review',
            'lock_version' => 1,
        ])->assertOk();

        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'paused-ok',
            'name' => 'Paused ok',
            'module_ids' => [$brokerId],
        ])->assertCreated()->json('data');

        $this->gatewayJson('POST', "/api/ib/v1/admin/plans/{$created['id']}/activate", [
            'reason' => 'Launch',
            'lock_version' => $created['lock_version'],
        ])->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_archived_plan_bindings_protect_modules_from_prune(): void
    {
        $legacy = ModuleRecord::factory()->create(['code' => 'legacy-source']);
        $created = $this->gatewayJson('POST', '/api/ib/v1/admin/plans', [
            'code' => 'legacy-plan',
            'name' => 'Legacy',
            'module_ids' => [(string) $legacy->id],
        ])->assertCreated()->json('data');

        $this->gatewayJson('DELETE', "/api/ib/v1/admin/plans/{$created['id']}", [
            'reason' => 'Archive with history',
            'lock_version' => $created['lock_version'],
        ])->assertNoContent();

        $this->artisan('modules:sync --prune --force')->assertExitCode(2);
        $this->assertDatabaseHas('modules', ['id' => $legacy->id, 'is_active' => false]);
    }

    private function brokerId(): string
    {
        return (string) ModuleRecord::query()->where('code', 'broker')->value('id');
    }
}
