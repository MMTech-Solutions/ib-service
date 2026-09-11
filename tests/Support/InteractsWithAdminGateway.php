<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait InteractsWithAdminGateway
{
    private const AUTHORIZED_SUB = '01993ac2-8750-73fd-b102-ba24fb06d8be';

    protected function seedAuthorizedAdmin(): void
    {
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

    protected function authorizedSub(): string
    {
        return self::AUTHORIZED_SUB;
    }

    /** @param array<string, mixed> $payload */
    protected function gatewayJson(string $method, string $uri, array $payload = [], ?string $sub = null): TestResponse
    {
        $userinfo = rtrim(strtr(base64_encode(json_encode([
            'sub' => $sub ?? self::AUTHORIZED_SUB,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $this->withHeaders([
            'X-Internal-Gateway' => 'test-gateway-secret',
            'X-Userinfo' => $userinfo,
        ])->json($method, $uri, $payload);
    }

    protected function assertGatewayAuthGuards(string $method, string $uri, array $payload = []): void
    {
        $this->json($method, $uri, $payload)->assertUnauthorized();
        $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
            'X-Internal-Source' => 'modules-tests',
        ])->json($method, $uri, $payload)->assertUnauthorized();

        $sub = '01993ac2-8750-73fd-b102-ba24fb06d8bf';
        DB::table('rbac_user_permission_snapshots')->insertOrIgnore([
            'message_key' => 'snapshot-'.$sub,
            'sub' => $sub,
            'surface' => 'admin_panel',
            'rev' => 1,
            'permissions' => '[]',
            'roles' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->gatewayJson($method, $uri, $payload, $sub)->assertForbidden();
    }
}
