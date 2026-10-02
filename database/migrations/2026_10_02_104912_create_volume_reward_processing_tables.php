<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_volume_reward_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->string('mode', 16)->default('periodic');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['program_id', 'starts_at'], 'program_volume_rewards_program_start_index');
        });
        Schema::create('volume_reward_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->timestampTz('occurred_from');
            $table->timestampTz('occurred_until');
            $table->text('cursor')->nullable();
            $table->string('status', 16);
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('claim_expires_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['module_id', 'status', 'claim_expires_at'], 'volume_reward_runs_claim_index');
        });
        Schema::create('volume_reward_event_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('order_id', 120);
            $table->string('external_trader_id', 120);
            $table->string('status', 16);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->jsonb('transport_snapshot')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['module_id', 'order_id', 'external_trader_id'], 'volume_reward_event_receipts_source_unique');
            $table->index(['status', 'next_attempt_at'], 'volume_reward_event_receipts_retry_index');
        });
        Schema::table('rewards', function (Blueprint $table): void {
            $table->string('origin_idempotency_key', 255)->nullable()->after('settlement_idempotency_key');
            $table->unique('origin_idempotency_key', 'rewards_origin_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropUnique('rewards_origin_idempotency_unique');
            $table->dropColumn('origin_idempotency_key');
        });
        Schema::dropIfExists('volume_reward_event_receipts');
        Schema::dropIfExists('volume_reward_runs');
        Schema::dropIfExists('program_volume_reward_configurations');
    }
};
