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
        if (! Schema::hasColumn('volume_reward_event_receipts', 'claim_token')) {
            Schema::table('volume_reward_event_receipts', function (Blueprint $table): void {
                $table->uuid('claim_token')->nullable();
            });
        }
        if (! Schema::hasColumn('volume_reward_event_receipts', 'claim_expires_at')) {
            Schema::table('volume_reward_event_receipts', function (Blueprint $table): void {
                $table->timestampTz('claim_expires_at')->nullable();
            });
        }
        if (! Schema::hasColumn('volume_reward_runs', 'attempt_count')) {
            Schema::table('volume_reward_runs', function (Blueprint $table): void {
                $table->unsignedInteger('attempt_count')->default(0);
            });
        }
        if (! Schema::hasColumn('volume_reward_runs', 'next_attempt_at')) {
            Schema::table('volume_reward_runs', function (Blueprint $table): void {
                $table->timestampTz('next_attempt_at')->nullable();
            });
        }

        $connection = app(ConnectionInterface::class);
        $connection->statement('DROP INDEX IF EXISTS volume_reward_receipts_claim_retry_index');
        $connection->statement('CREATE INDEX volume_reward_receipts_claim_retry_index ON volume_reward_event_receipts (status, next_attempt_at, claim_expires_at)');
        $connection->statement('DROP INDEX IF EXISTS volume_reward_runs_claim_index');
        $connection->statement('CREATE INDEX volume_reward_runs_claim_index ON volume_reward_runs (module_id, status, next_attempt_at, claim_expires_at)');
        $connection->statement('ALTER TABLE program_volume_reward_configurations DROP CONSTRAINT IF EXISTS program_volume_reward_mode_check');
        $connection->statement('ALTER TABLE volume_reward_runs DROP CONSTRAINT IF EXISTS volume_reward_runs_status_check');
        $connection->statement('ALTER TABLE volume_reward_event_receipts DROP CONSTRAINT IF EXISTS volume_reward_receipts_status_check');
        $connection->statement("ALTER TABLE program_volume_reward_configurations ADD CONSTRAINT program_volume_reward_mode_check CHECK (mode IN ('periodic', 'event', 'both'))");
        $connection->statement("ALTER TABLE volume_reward_runs ADD CONSTRAINT volume_reward_runs_status_check CHECK (status IN ('pending', 'processing', 'retryable', 'completed'))");
        $connection->statement("ALTER TABLE volume_reward_event_receipts ADD CONSTRAINT volume_reward_receipts_status_check CHECK (status IN ('pending', 'processing', 'retryable', 'processed', 'rejected'))");
        $connection->statement('DROP INDEX IF EXISTS program_volume_reward_configurations_active_unique');
        $connection->statement('DROP INDEX IF EXISTS program_volume_reward_current_unique');
        $connection->statement('CREATE UNIQUE INDEX program_volume_reward_current_unique ON program_volume_reward_configurations (program_id) WHERE ends_at IS NULL');
        $connection->statement('ALTER TABLE rewards DROP CONSTRAINT IF EXISTS rewards_origin_idempotency_unique');
        $connection->statement('DROP INDEX IF EXISTS rewards_origin_idempotency_unique');
        $connection->statement('CREATE UNIQUE INDEX rewards_origin_idempotency_unique ON rewards (origin_idempotency_key) WHERE origin_idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        $connection = app(ConnectionInterface::class);
        $connection->statement('DROP INDEX IF EXISTS rewards_origin_idempotency_unique');
        $connection->statement('ALTER TABLE rewards ADD CONSTRAINT rewards_origin_idempotency_unique UNIQUE (origin_idempotency_key)');
        $connection->statement('DROP INDEX IF EXISTS program_volume_reward_current_unique');
        $connection->statement('CREATE UNIQUE INDEX program_volume_reward_configurations_active_unique ON program_volume_reward_configurations (program_id) WHERE ends_at IS NULL');
        $connection->statement('ALTER TABLE volume_reward_event_receipts DROP CONSTRAINT IF EXISTS volume_reward_receipts_status_check');
        $connection->statement('ALTER TABLE volume_reward_runs DROP CONSTRAINT IF EXISTS volume_reward_runs_status_check');
        $connection->statement('ALTER TABLE program_volume_reward_configurations DROP CONSTRAINT IF EXISTS program_volume_reward_mode_check');

        $connection->statement('DROP INDEX IF EXISTS volume_reward_receipts_claim_retry_index');
        $connection->statement('DROP INDEX IF EXISTS volume_reward_runs_claim_index');
        $connection->statement('CREATE INDEX volume_reward_runs_claim_index ON volume_reward_runs (module_id, status, claim_expires_at)');
        Schema::table('volume_reward_event_receipts', function (Blueprint $table): void {
            $table->dropColumn(['claim_token', 'claim_expires_at']);
        });
        Schema::table('volume_reward_runs', function (Blueprint $table): void {
            $table->dropColumn(['attempt_count', 'next_attempt_at']);
        });
    }
};
