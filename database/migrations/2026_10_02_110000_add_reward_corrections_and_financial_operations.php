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
            $table->foreignUuid('compensates_reward_id')->nullable()->after('id')->constrained('rewards')->restrictOnDelete();
            $table->string('reconciliation_hold_code', 64)->nullable()->after('settlement_lock_expires_at');
            $table->timestampTz('reconciliation_hold_at')->nullable()->after('reconciliation_hold_code');
            $table->timestampTz('last_reconciled_at')->nullable()->after('reconciliation_hold_at');
            $table->index('compensates_reward_id', 'rewards_compensation_origin_index');
            $table->index('reconciliation_hold_at', 'rewards_reconciliation_hold_index');
        });

        Schema::create('reward_financial_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reward_id')->constrained('rewards')->restrictOnDelete();
            $table->foreignUuid('compensation_reward_id')->nullable()->constrained('rewards')->restrictOnDelete();
            $table->string('operation_type', 16);
            $table->string('status', 16);
            $table->string('idempotency_key', 191)->unique();
            $table->uuid('requested_by_user_id');
            $table->string('reason_code', 64);
            $table->string('reason_label', 255)->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('currency_code', 16)->nullable();
            $table->unsignedTinyInteger('currency_precision')->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('provider_reference_id', 191)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestampTz('lock_expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['reward_id', 'operation_type'], 'reward_financial_operation_once');
            $table->index(['status', 'lock_expires_at'], 'reward_financial_operations_pending_index');
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement('ALTER TABLE rewards DROP CONSTRAINT IF EXISTS rewards_status_check');
            app(ConnectionInterface::class)->statement("ALTER TABLE rewards ADD CONSTRAINT rewards_status_check CHECK (status IN ('pending', 'failed', 'settled', 'cancelled', 'reversal_pending', 'reversal_failed', 'reversed'))");
            app(ConnectionInterface::class)->statement("ALTER TABLE reward_financial_operations ADD CONSTRAINT reward_financial_operations_type_check CHECK (operation_type IN ('cancellation', 'reversal', 'compensation'))");
            app(ConnectionInterface::class)->statement("ALTER TABLE reward_financial_operations ADD CONSTRAINT reward_financial_operations_status_check CHECK (status IN ('processing', 'failed', 'completed'))");
        }
    }

    public function down(): void
    {
        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement('ALTER TABLE reward_financial_operations DROP CONSTRAINT IF EXISTS reward_financial_operations_status_check');
            app(ConnectionInterface::class)->statement('ALTER TABLE reward_financial_operations DROP CONSTRAINT IF EXISTS reward_financial_operations_type_check');
            app(ConnectionInterface::class)->statement('ALTER TABLE rewards DROP CONSTRAINT IF EXISTS rewards_status_check');
            app(ConnectionInterface::class)->statement("ALTER TABLE rewards ADD CONSTRAINT rewards_status_check CHECK (status IN ('pending', 'failed', 'settled'))");
        }

        Schema::dropIfExists('reward_financial_operations');
        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropIndex('rewards_compensation_origin_index');
            $table->dropIndex('rewards_reconciliation_hold_index');
            $table->dropConstrainedForeignId('compensates_reward_id');
            $table->dropColumn(['reconciliation_hold_code', 'reconciliation_hold_at', 'last_reconciled_at']);
        });
    }
};
