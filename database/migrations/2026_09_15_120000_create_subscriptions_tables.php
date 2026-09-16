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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('external_user_id');
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('origin', 32);
            $table->boolean('requires_approval')->nullable();
            $table->string('status', 16);
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->uuid('replaces_subscription_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['external_user_id', 'created_at', 'id']);
            $table->index(['status', 'created_at', 'id']);
            $table->index(['plan_id', 'status', 'created_at', 'id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('replaces_subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->restrictOnDelete();
        });

        Schema::create('subscription_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->boolean('is_fixed')->default(false);
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_until')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['subscription_id', 'effective_from', 'id']);
            $table->index(['program_id', 'effective_from', 'id']);
        });

        Schema::create('subscription_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_id');
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('action', 32);
            $table->string('actor_kind', 16);
            $table->uuid('actor_external_user_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('previous_status', 16)->nullable();
            $table->string('next_status', 16)->nullable();
            $table->uuid('previous_program_id')->nullable();
            $table->uuid('next_program_id')->nullable();
            $table->boolean('previous_is_fixed')->nullable();
            $table->boolean('next_is_fixed')->nullable();
            $table->timestampTz('occurred_at');

            $table->foreign('previous_program_id')->references('id')->on('programs')->restrictOnDelete();
            $table->foreign('next_program_id')->references('id')->on('programs')->restrictOnDelete();

            $table->index(['subscription_id', 'occurred_at', 'id']);
            $table->index(['operation_id']);
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            $connection = app(ConnectionInterface::class);

            $connection->statement(
                'ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_lock_version_check CHECK (lock_version > 0)'
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_origin_shape_check CHECK (
                    (origin = 'user_application' AND requires_approval IS NOT NULL AND replaces_subscription_id IS NULL)
                    OR (origin = 'admin_plan_change' AND requires_approval IS NULL AND replaces_subscription_id IS NOT NULL)
                )
                SQL
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_shape_check CHECK (
                    (status = 'pending' AND activated_at IS NULL AND closed_at IS NULL)
                    OR (status = 'active' AND activated_at IS NOT NULL AND closed_at IS NULL)
                    OR (status = 'rejected' AND activated_at IS NULL AND closed_at IS NOT NULL)
                    OR (status = 'ended' AND activated_at IS NOT NULL AND closed_at IS NOT NULL)
                )
                SQL
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_timestamp_order_check CHECK (
                    (activated_at IS NULL OR activated_at >= created_at)
                    AND (closed_at IS NULL OR closed_at >= created_at)
                    AND (activated_at IS NULL OR closed_at IS NULL OR closed_at >= activated_at)
                )
                SQL
            );
            $connection->statement(
                'ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_replaces_not_self_check CHECK (replaces_subscription_id IS NULL OR replaces_subscription_id <> id)'
            );
            $connection->statement(
                "CREATE UNIQUE INDEX subscriptions_open_user_unique ON subscriptions (external_user_id) WHERE status IN ('pending', 'active')"
            );
            $connection->statement(
                'CREATE UNIQUE INDEX subscriptions_replaces_unique ON subscriptions (replaces_subscription_id) WHERE replaces_subscription_id IS NOT NULL'
            );
            $connection->statement(
                "CREATE INDEX subscriptions_open_plan_index ON subscriptions (plan_id) WHERE status IN ('pending', 'active')"
            );

            $connection->statement(
                'ALTER TABLE subscription_placements ADD CONSTRAINT subscription_placements_interval_check CHECK (effective_until IS NULL OR effective_until >= effective_from)'
            );
            $connection->statement(
                'CREATE UNIQUE INDEX subscription_placements_open_unique ON subscription_placements (subscription_id) WHERE effective_until IS NULL'
            );

            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_actor_check CHECK (
                    (actor_kind = 'iam' AND actor_external_user_id IS NOT NULL)
                    OR (actor_kind = 'system' AND actor_external_user_id IS NULL)
                )
                SQL
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_reject_reason_check CHECK (
                    action <> 'reject' OR (reason IS NOT NULL AND btrim(reason) <> '')
                )
                SQL
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_placement_snapshot_check CHECK (
                    ((previous_program_id IS NULL) = (previous_is_fixed IS NULL))
                    AND ((next_program_id IS NULL) = (next_is_fixed IS NULL))
                )
                SQL
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_status_catalog_check CHECK (
                    (previous_status IS NULL OR previous_status IN ('pending', 'active', 'rejected', 'ended'))
                    AND (next_status IS NULL OR next_status IN ('pending', 'active', 'rejected', 'ended'))
                )
                SQL
            );
            $connection->statement(
                <<<'SQL'
                ALTER TABLE subscription_changes ADD CONSTRAINT subscription_changes_action_catalog_check CHECK (
                    action IN (
                        'request',
                        'approve',
                        'reject',
                        'cancel',
                        'change_plan_out',
                        'change_plan_in',
                        'change_program',
                        'fix_placement',
                        'release_placement'
                    )
                )
                SQL
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
        Schema::dropIfExists('subscription_placements');
        Schema::dropIfExists('subscriptions');
    }
};
