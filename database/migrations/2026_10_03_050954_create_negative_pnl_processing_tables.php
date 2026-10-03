<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negative_pnl_discovery_cursors', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->uuid('configuration_after')->nullable();
            $table->uuid('subscription_after')->nullable();
            $table->timestampTz('started_at', 6)->nullable();
        });
        Schema::create('negative_pnl_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identity_key', 64)->unique();
            $table->uuid('subscription_id');
            $table->uuid('beneficiary_id');
            $table->uuid('plan_id');
            $table->uuid('module_id');
            $table->string('server_group_id', 191);
            $table->string('cadence', 16);
            $table->timestampTz('next_cut_at', 6);
            $table->timestampTz('cursor_at', 6)->nullable();
            $table->timestampTz('closed_at', 6)->nullable();
            $table->boolean('reset_baseline')->default(false);
            $table->uuid('lease_token')->nullable();
            $table->timestampTz('lease_expires_at', 6)->nullable();
            $table->timestampTz('retry_at', 6)->nullable();
            $table->timestampTz('finished_at', 6)->nullable();
            $table->string('error_code', 96)->nullable();
            $table->timestampTz('last_attempt_at', 6)->nullable();
            $table->index(['finished_at', 'retry_at', 'next_cut_at']);
        });
        Schema::create('negative_pnl_baselines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('negative_pnl_jobs')->restrictOnDelete();
            $table->uuid('referral_id');
            $table->string('account_id', 191);
            $table->string('currency_code', 3);
            $table->integer('currency_precision');
            $table->text('balance_after');
            $table->timestampTz('occurred_until', 6);
            $table->unique(['job_id', 'referral_id', 'account_id']);
        });
        Schema::create('negative_pnl_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('negative_pnl_jobs')->restrictOnDelete();
            $table->timestampTz('occurred_until', 6);
            $table->string('status', 16);
            $table->jsonb('inputs');
            $table->jsonb('receipts');
            $table->jsonb('outcomes')->default('{}');
            $table->unique(['job_id', 'occurred_until']);
        });
        Schema::create('negative_pnl_pending_closures', function (Blueprint $table): void {
            $table->uuid('subscription_id')->primary();
            $table->uuid('incoming_subscription_id');
            $table->uuid('operation_id')->unique();
            $table->timestampTz('closed_at', 6);
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampTz('discovered_at', 6)->nullable();
        });
        DB::statement("ALTER TABLE negative_pnl_jobs ADD CONSTRAINT negative_pnl_jobs_cadence_check CHECK (cadence IN ('daily', 'weekly', 'monthly', 'yearly'))");
        DB::statement("ALTER TABLE negative_pnl_periods ADD CONSTRAINT negative_pnl_periods_status_check CHECK (status IN ('preparing', 'ready', 'completed'))");
        DB::statement('ALTER TABLE negative_pnl_baselines ADD CONSTRAINT negative_pnl_baselines_precision_check CHECK (currency_precision BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('negative_pnl_pending_closures');
        Schema::dropIfExists('negative_pnl_periods');
        Schema::dropIfExists('negative_pnl_baselines');
        Schema::dropIfExists('negative_pnl_jobs');
        Schema::dropIfExists('negative_pnl_discovery_cursors');
    }
};
