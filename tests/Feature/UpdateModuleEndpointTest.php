<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class UpdateModuleEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_authorized_operator_can_update_only_administrative_fields(): void
    {
        $moduleId = $this->brokerId();

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/modules/{$moduleId}", [
            'name' => 'Broker module',
            'description' => 'Administrative description',
            'lock_version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Broker module')
            ->assertJsonPath('data.description', 'Administrative description')
            ->assertJsonPath('data.lock_version', 2);

        $this->assertDatabaseHas('modules', [
            'id' => $moduleId,
            'name' => 'Broker module',
            'description' => 'Administrative description',
            'lock_version' => 2,
        ]);
        $this->assertDatabaseCount('module_operational_changes', 0);
    }

    public function test_update_is_idempotent_without_incrementing_lock_version(): void
    {
        $moduleId = $this->brokerId();
        $module = DB::table('modules')->where('id', $moduleId)->first();
        self::assertNotNull($module);

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/modules/{$moduleId}", [
            'name' => (string) $module->name,
            'description' => $module->description,
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.lock_version', 1);
    }

    public function test_update_supports_a_description_only_patch(): void
    {
        $moduleId = $this->brokerId();
        $name = (string) DB::table('modules')->where('id', $moduleId)->value('name');

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/modules/{$moduleId}", [
            'description' => 'Description only',
            'lock_version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.description', 'Description only')
            ->assertJsonPath('data.lock_version', 2);
    }

    public function test_update_rejects_immutable_fields_and_stale_versions(): void
    {
        $moduleId = $this->brokerId();

        $this->gatewayJson('PATCH', "/api/ib/v1/admin/modules/{$moduleId}", [
            'name' => 'Changed',
            'lock_version' => 1,
            'code' => 'replacement',
        ])->assertUnprocessable();

        DB::table('modules')->where('id', $moduleId)->update(['lock_version' => 2]);
        $this->gatewayJson('PATCH', "/api/ib/v1/admin/modules/{$moduleId}", [
            'name' => 'Changed',
            'lock_version' => 1,
        ])->assertConflict()->assertJsonPath('error.code', 'MODULE_CONCURRENCY_CONFLICT');
    }

    public function test_update_route_enforces_gateway_identity_and_permission(): void
    {
        $this->assertGatewayAuthGuards('PATCH', "/api/ib/v1/admin/modules/{$this->brokerId()}", [
            'name' => 'Changed',
            'lock_version' => 1,
        ]);
    }

    private function brokerId(): string
    {
        return (string) DB::table('modules')->where('code', 'broker')->value('id');
    }
}
