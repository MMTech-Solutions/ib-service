<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithAdminGateway;
use Tests\TestCase;

final class ShowModuleEndpointTest extends TestCase
{
    use InteractsWithAdminGateway;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizedAdmin();
    }

    public function test_authorized_operator_can_show_a_module_with_capabilities_and_lock_version(): void
    {
        $moduleId = $this->brokerId();

        $this->gatewayJson('GET', "/api/ib/v1/admin/modules/{$moduleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $moduleId)
            ->assertJsonPath('data.code', 'broker')
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonCount(2, 'data.capabilities');
    }

    public function test_unknown_module_returns_a_normalized_not_found_error(): void
    {
        $this->gatewayJson('GET', '/api/ib/v1/admin/modules/'.Str::uuid7())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'MODULE_NOT_FOUND');
    }

    public function test_malformed_module_id_is_rejected(): void
    {
        $this->gatewayJson('GET', '/api/ib/v1/admin/modules/not-a-uuid')->assertUnprocessable();
    }

    public function test_show_route_enforces_gateway_identity_and_permission(): void
    {
        $this->assertGatewayAuthGuards('GET', "/api/ib/v1/admin/modules/{$this->brokerId()}");
    }

    private function brokerId(): string
    {
        return (string) DB::table('modules')->where('code', 'broker')->value('id');
    }
}
