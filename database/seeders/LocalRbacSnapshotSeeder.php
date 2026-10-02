<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LocalRbacSnapshotSeeder extends Seeder
{
    /** Aligns with Keycloak user created by broker/docker/keycloak-bff-bootstrap.sh + mmtech:init */
    public const ADMIN_SUB = 'a9280aeb-70d9-43bb-b9ac-4acde4dc61c5';

    public const CUSTOMER_SUB = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaa0';

    public function run(): void
    {
        $now = now('UTC');

        DB::table(config('rbac.store.table', 'rbac_user_permission_snapshots'))->upsert(
            [
                [
                    'message_key' => 'local-postman:admin_panel:'.self::ADMIN_SUB,
                    'sub' => self::ADMIN_SUB,
                    'surface' => 'admin_panel',
                    'rev' => 1,
                    'permissions' => json_encode(['ib.modules.manage', 'ib.plans.manage', 'ib.programs.manage', 'ib.rules.manage', 'ib.subscriptions.manage', 'ib.progression.read', 'ib.rewards.manage'], JSON_THROW_ON_ERROR),
                    'roles' => json_encode([
                        ['id' => '019f3802-74b2-713f-bbbb-0e4d3f5409e5', 'name' => 'super-admin'],
                    ], JSON_THROW_ON_ERROR),
                    'snapshot_updated_at' => $now->toISOString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'message_key' => 'local-postman:customer_app:'.self::CUSTOMER_SUB,
                    'sub' => self::CUSTOMER_SUB,
                    'surface' => 'customer_app',
                    'rev' => 1,
                    'permissions' => json_encode([
                        'customer.frontend.access',
                        'customer.profile.read',
                        'ib.subscriptions.apply',
                        'ib.subscriptions.read',
                    ], JSON_THROW_ON_ERROR),
                    'roles' => json_encode([
                        ['id' => '019f3802-74cb-7387-b76d-97950a8984eb', 'name' => 'customer'],
                    ], JSON_THROW_ON_ERROR),
                    'snapshot_updated_at' => $now->toISOString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['sub', 'surface'],
            ['message_key', 'rev', 'permissions', 'roles', 'snapshot_updated_at', 'updated_at'],
        );
    }
}
