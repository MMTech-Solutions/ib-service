<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $connection = app(ConnectionInterface::class);
        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('ALTER TABLE subscription_changes DROP CONSTRAINT subscription_changes_action_catalog_check');
            $connection->statement("ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_action_catalog_check CHECK (action IN ('request', 'approve', 'reject', 'cancel', 'change_plan_out', 'change_plan_in', 'change_program', 'fix_placement', 'release_placement', 'progression_placement'))");
        }
    }

    public function down(): void
    {
        $connection = app(ConnectionInterface::class);
        if ($connection->getDriverName() === 'pgsql') {
            if ($connection->table('subscription_changes')->where('action', 'progression_placement')->exists()) {
                throw new RuntimeException('Cannot remove progression_placement while audit rows exist.');
            }
            $connection->statement('ALTER TABLE subscription_changes DROP CONSTRAINT subscription_changes_action_catalog_check');
            $connection->statement("ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_action_catalog_check CHECK (action IN ('request', 'approve', 'reject', 'cancel', 'change_plan_out', 'change_plan_in', 'change_program', 'fix_placement', 'release_placement'))");
        }
    }
};
