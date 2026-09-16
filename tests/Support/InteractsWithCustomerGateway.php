<?php

declare(strict_types=1);

namespace Tests\Support;

use Database\Seeders\LocalRbacSnapshotSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait InteractsWithCustomerGateway
{
    protected function seedAuthorizedCustomer(?string $sub = null, array $permissions = [
        'ib.subscriptions.apply',
        'ib.subscriptions.read',
    ]): string
    {
        config()->set('rbac.internal.token', 'test-internal-token');
        config()->set('rbac.gateway.internal_secret', 'test-gateway-secret');
        config()->set('rbac.fallback.enabled', false);

        $customerSub = $sub ?? LocalRbacSnapshotSeeder::CUSTOMER_SUB;

        DB::table('rbac_user_permission_snapshots')->where('sub', $customerSub)->where('surface', 'customer_app')->delete();
        DB::table('rbac_user_permission_snapshots')->insert([
            'message_key' => 'snapshot-customer-'.$customerSub,
            'sub' => $customerSub,
            'surface' => 'customer_app',
            'rev' => 1,
            'permissions' => json_encode(array_values($permissions), JSON_THROW_ON_ERROR),
            'roles' => '[]',
            'snapshot_updated_at' => now('UTC')->toISOString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $customerSub;
    }

    /** @param array<string, mixed> $payload */
    protected function customerGatewayJson(
        string $method,
        string $uri,
        array $payload = [],
        ?string $sub = null,
    ): TestResponse {
        $userinfo = rtrim(strtr(base64_encode(json_encode([
            'sub' => $sub ?? LocalRbacSnapshotSeeder::CUSTOMER_SUB,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $this->withHeaders([
            'X-Internal-Gateway' => 'test-gateway-secret',
            'X-Userinfo' => $userinfo,
        ])->json($method, $uri, $payload);
    }

    protected function assertCustomerGatewayAuthGuards(string $method, string $uri, array $payload = []): void
    {
        $this->flushHeaders();
        $this->json($method, $uri, $payload)->assertUnauthorized();
        $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
            'X-Internal-Source' => 'subscriptions-tests',
        ])->json($method, $uri, $payload)->assertUnauthorized();

        $sub = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbb1';
        $this->seedAuthorizedCustomer($sub, []);
        $this->customerGatewayJson($method, $uri, $payload, $sub)->assertForbidden();
    }
}
