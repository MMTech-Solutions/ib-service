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
        Schema::table('rewards', function (Blueprint $table): void {
            $table->string('settlement_provider', 64)->nullable()->after('status');
            $table->string('settlement_reference_id', 191)->nullable()->after('settlement_provider');
            $table->string('settlement_idempotency_key', 191)->nullable()->unique()->after('settlement_reference_id');
            $table->unsignedInteger('settlement_attempt_count')->default(0)->after('settlement_idempotency_key');
            $table->timestampTz('last_settlement_attempt_at')->nullable()->after('settlement_attempt_count');
            $table->string('last_settlement_error_code', 64)->nullable()->after('last_settlement_attempt_at');
            $table->timestampTz('settled_at')->nullable()->after('last_settlement_error_code');
            $table->uuid('settlement_lock_token')->nullable()->after('settled_at');
            $table->timestampTz('settlement_lock_expires_at')->nullable()->after('settlement_lock_token');
            $table->index(['status', 'last_settlement_attempt_at'], 'rewards_settlement_selection_index');
            $table->index('settlement_lock_expires_at', 'rewards_settlement_lease_index');
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement(
                "ALTER TABLE rewards ADD CONSTRAINT rewards_status_check CHECK (status IN ('pending', 'failed', 'settled'))"
            );
        }
    }

    public function down(): void
    {
        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement('ALTER TABLE rewards DROP CONSTRAINT IF EXISTS rewards_status_check');
        }

        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropIndex('rewards_settlement_selection_index');
            $table->dropIndex('rewards_settlement_lease_index');
            $table->dropUnique(['settlement_idempotency_key']);
            $table->dropColumn([
                'settlement_provider',
                'settlement_reference_id',
                'settlement_idempotency_key',
                'settlement_attempt_count',
                'last_settlement_attempt_at',
                'last_settlement_error_code',
                'settled_at',
                'settlement_lock_token',
                'settlement_lock_expires_at',
            ]);
        });
    }
};
