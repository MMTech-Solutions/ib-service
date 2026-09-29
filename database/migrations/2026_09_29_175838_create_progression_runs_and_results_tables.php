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
        Schema::create('progression_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->timestampTz('window_starts_at');
            $table->timestampTz('window_ends_at');
            $table->string('status', 32);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['plan_id', 'window_starts_at', 'window_ends_at'], 'progression_runs_window_unique');
            $table->index(['plan_id', 'window_ends_at'], 'progression_runs_plan_window_idx');
            $table->index(['status', 'updated_at'], 'progression_runs_status_updated_idx');
        });

        Schema::create('progression_run_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('progression_runs')->restrictOnDelete();
            $table->foreignUuid('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('status', 16);
            $table->decimal('total_points', 24, 8)->nullable();
            $table->foreignUuid('target_program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('failure_message', 512)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['run_id', 'subscription_id'], 'progression_run_results_subscription_unique');
            $table->index(['run_id', 'status'], 'progression_run_results_run_status_idx');
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            $connection = app(ConnectionInterface::class);
            $connection->statement("ALTER TABLE progression_runs ADD CONSTRAINT progression_runs_status_check CHECK (status IN ('pending', 'running', 'completed', 'completed_with_errors'))");
            $connection->statement('ALTER TABLE progression_runs ADD CONSTRAINT progression_runs_window_order_check CHECK (window_ends_at > window_starts_at)');
            $connection->statement("ALTER TABLE progression_run_results ADD CONSTRAINT progression_run_results_status_check CHECK (status IN ('completed', 'skipped', 'failed'))");
            $connection->statement("ALTER TABLE progression_run_results ADD CONSTRAINT progression_run_results_final_shape_check CHECK ((status IN ('completed', 'skipped') AND completed_at IS NOT NULL AND total_points IS NOT NULL) OR (status = 'failed' AND completed_at IS NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_run_results');
        Schema::dropIfExists('progression_runs');
    }
};
