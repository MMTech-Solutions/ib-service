<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ListModulesEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORIZED_SUB = '01993ac2-8750-73fd-b102-ba24fb06d8be';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('rbac.internal.token', 'test-internal-token');
        config()->set('rbac.gateway.internal_secret', 'test-gateway-secret');
        config()->set('rbac.fallback.enabled', false);
        $this->artisan('modules:sync')->assertExitCode(0);
        DB::table('rbac_user_permission_snapshots')->insert([
            'message_key' => 'snapshot-'.self::AUTHORIZED_SUB,
            'sub' => self::AUTHORIZED_SUB,
            'surface' => 'admin_panel',
            'rev' => 1,
            'permissions' => json_encode(['ib.modules.manage'], JSON_THROW_ON_ERROR),
            'roles' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_gateway_user_with_permission_can_list_modules(): void
    {
        $response = $this->gatewayGet(self::AUTHORIZED_SUB);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.code', 'broker')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.processing_status', 'running')
            ->assertJsonPath('data.0.lock_version', 1)
            ->assertJsonCount(2, 'data.0.capabilities')
            ->assertJsonPath('meta.pagination.per_page', 100)
            ->assertJsonPath('meta.filters', []);
    }

    public function test_it_filters_and_validates_the_list_query(): void
    {
        $this->gatewayGet(self::AUTHORIZED_SUB, ['search' => 'missing'])->assertOk()->assertJsonCount(0, 'data');
        $this->gatewayGet(self::AUTHORIZED_SUB, ['is_active' => '0'])->assertOk()->assertJsonCount(0, 'data');
        $this->gatewayGet(self::AUTHORIZED_SUB, ['processing_status' => 'running'])->assertOk()->assertJsonCount(1, 'data');
        $this->gatewayGet(self::AUTHORIZED_SUB, ['per_page' => 101])->assertUnprocessable();
    }

    public function test_it_includes_inactive_capabilities(): void
    {
        DB::table('module_capabilities')
            ->where('code', 'deposits')
            ->update(['is_active' => false]);

        $capabilities = collect($this->gatewayGet(self::AUTHORIZED_SUB)->assertOk()->json('data.0.capabilities'))
            ->keyBy('code');

        self::assertFalse($capabilities->get('deposits')['is_active']);
        self::assertTrue($capabilities->has('closed_trading_volume'));
    }

    public function test_request_without_identity_is_rejected(): void
    {
        $this->getJson('/api/ib/v1/admin/modules')->assertUnauthorized();
    }

    public function test_internal_headers_do_not_bypass_gateway_authentication(): void
    {
        $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
            'X-Internal-Source' => 'modules-tests',
        ])->getJson('/api/ib/v1/admin/modules')->assertUnauthorized();
    }

    public function test_gateway_user_without_permission_is_forbidden(): void
    {
        $sub = '01993ac2-8750-73fd-b102-ba24fb06d8bf';
        DB::table('rbac_user_permission_snapshots')->insert([
            'message_key' => 'snapshot-'.$sub,
            'sub' => $sub,
            'surface' => 'admin_panel',
            'rev' => 1,
            'permissions' => '[]',
            'roles' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userinfo = rtrim(strtr(base64_encode(json_encode(['sub' => $sub], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $this->withHeaders([
            'X-Internal-Gateway' => 'test-gateway-secret',
            'X-Userinfo' => $userinfo,
        ])->getJson('/api/ib/v1/admin/modules')->assertForbidden();
    }

    /** @param array<string, scalar> $query */
    private function gatewayGet(string $sub, array $query = []): TestResponse
    {
        $userinfo = rtrim(strtr(base64_encode(json_encode(['sub' => $sub], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $this->withHeaders([
            'X-Internal-Gateway' => 'test-gateway-secret',
            'X-Userinfo' => $userinfo,
        ])->getJson('/api/ib/v1/admin/modules'.($query === [] ? '' : '?'.http_build_query($query)));
    }
}
