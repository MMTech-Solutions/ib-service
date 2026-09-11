<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\LocalRbacSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LocalRbacSnapshotSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_idempotent_admin_and_customer_snapshots(): void
    {
        $this->seed(LocalRbacSnapshotSeeder::class);
        $this->seed(LocalRbacSnapshotSeeder::class);

        $snapshots = DB::table('rbac_user_permission_snapshots')
            ->whereIn('sub', [LocalRbacSnapshotSeeder::ADMIN_SUB, LocalRbacSnapshotSeeder::CUSTOMER_SUB])
            ->orderBy('surface')
            ->get();

        self::assertCount(2, $snapshots);
        self::assertSame('admin_panel', $snapshots[0]->surface);
        self::assertSame(['ib.modules.manage'], json_decode($snapshots[0]->permissions, true, flags: JSON_THROW_ON_ERROR));
        self::assertSame('customer_app', $snapshots[1]->surface);
        self::assertSame(
            ['customer.frontend.access', 'customer.profile.read'],
            json_decode($snapshots[1]->permissions, true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
