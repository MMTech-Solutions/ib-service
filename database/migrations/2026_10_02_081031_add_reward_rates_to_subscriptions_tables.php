<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->decimal('personal_rate', 9, 8)->default('1');
            $table->boolean('is_master')->default(false);
            $table->decimal('master_rate', 16, 8)->default('1');
        });
        Schema::table('subscription_changes', function (Blueprint $table): void {
            $table->decimal('previous_personal_rate', 9, 8)->nullable();
            $table->boolean('previous_is_master')->nullable();
            $table->decimal('previous_master_rate', 16, 8)->nullable();
            $table->decimal('next_personal_rate', 9, 8)->nullable();
            $table->boolean('next_is_master')->nullable();
            $table->decimal('next_master_rate', 16, 8)->nullable();
        });

        $connection = app(ConnectionInterface::class);
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_personal_rate_check CHECK (personal_rate >= 0 AND personal_rate <= 1)');
        $connection->statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_master_rate_check CHECK (master_rate >= 1)');
        $connection->statement('ALTER TABLE subscription_changes DROP CONSTRAINT subscription_changes_action_catalog_check');
        $connection->statement("ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_action_catalog_check CHECK (action IN ('request', 'approve', 'reject', 'cancel', 'change_plan_out', 'change_plan_in', 'change_program', 'fix_placement', 'release_placement', 'progression_placement', 'update_reward_rates'))");
    }

    public function down(): void
    {
        $connection = app(ConnectionInterface::class);
        if ($connection->getDriverName() === 'pgsql') {
            if ($connection->table('subscription_changes')->where('action', 'update_reward_rates')->exists()) {
                throw new RuntimeException('Cannot remove update_reward_rates while audit rows exist.');
            }

            $connection->statement('ALTER TABLE subscription_changes DROP CONSTRAINT subscription_changes_action_catalog_check');
            $connection->statement("ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_action_catalog_check CHECK (action IN ('request', 'approve', 'reject', 'cancel', 'change_plan_out', 'change_plan_in', 'change_program', 'fix_placement', 'release_placement', 'progression_placement'))");
            $connection->statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_personal_rate_check');
            $connection->statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_master_rate_check');
        }

        Schema::table('subscription_changes', function (Blueprint $table): void {
            $table->dropColumn([
                'previous_personal_rate',
                'previous_is_master',
                'previous_master_rate',
                'next_personal_rate',
                'next_is_master',
                'next_master_rate',
            ]);
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['personal_rate', 'is_master', 'master_rate']);
        });
    }
};
