<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scheduling_tasks', function (Blueprint $table) {
            $table->string('code', 120)->primary();
            $table->text('description');
            $table->string('cron_expression', 120);
            $table->boolean('automatic_enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('updated_at', 6)->nullable();
        });
        Schema::create('scheduling_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_code', 120);
            $table->foreign('task_code')->references('code')->on('scheduling_tasks');
            $table->string('origin', 20);
            $table->string('status', 20);
            $table->jsonb('configuration');
            $table->string('actor_id', 120)->nullable();
            $table->text('reason')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('request_hash', 64)->nullable();
            foreach (['accepted_at', 'scheduled_at', 'started_at', 'heartbeat_at', 'finished_at'] as $column) {
                $table->timestampTz($column, 6)->nullable();
            }
            $table->integer('exit_code')->nullable();
            $table->string('outcome', 100)->nullable();
            $table->text('stdout')->default('');
            $table->text('stderr')->default('');
            $table->boolean('stdout_truncated')->default(false);
            $table->boolean('stderr_truncated')->default(false);
            $table->unsignedInteger('timeout_seconds');
            $table->index(['task_code', 'accepted_at']);
            $table->index(['status', 'heartbeat_at']);
            $table->index(['origin', 'accepted_at']);
        });
        DB::statement("CREATE UNIQUE INDEX scheduling_active_task_unique ON scheduling_runs (task_code) WHERE status IN ('queued', 'running')");
        DB::statement("CREATE UNIQUE INDEX scheduling_automatic_slot_unique ON scheduling_runs (task_code, scheduled_at) WHERE origin = 'automatic'");
        DB::statement("ALTER TABLE scheduling_runs ADD CONSTRAINT scheduling_run_status_check CHECK (status IN ('queued', 'running', 'succeeded', 'failed', 'skipped', 'interrupted'))");
        DB::statement("ALTER TABLE scheduling_runs ADD CONSTRAINT scheduling_run_origin_check CHECK (origin IN ('manual', 'automatic'))");
        Schema::create('scheduling_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('task_code', 120);
            $table->foreign('task_code')->references('code')->on('scheduling_tasks');
            $table->string('actor_id', 120);
            $table->text('reason');
            $table->timestampTz('occurred_at', 6);
            $table->jsonb('before');
            $table->jsonb('after');
            $table->index(['task_code', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduling_audits');
        Schema::dropIfExists('scheduling_runs');
        Schema::dropIfExists('scheduling_tasks');
    }
};
