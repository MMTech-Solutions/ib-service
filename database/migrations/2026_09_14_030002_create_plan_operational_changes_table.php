<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_operational_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('action', 16);
            $table->string('actor_kind', 16);
            $table->uuid('actor_iam_id')->nullable();
            $table->string('reason', 500);
            $table->boolean('previous_is_active');
            $table->boolean('next_is_active');
            $table->uuid('cause_event_id')->nullable();
            $table->uuid('cause_module_id')->nullable();
            $table->uuid('initiating_actor_iam_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->index(['plan_id', 'occurred_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE plan_operational_changes ADD CONSTRAINT plan_operational_changes_action_check CHECK (action IN ('activate', 'deactivate', 'archive'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE plan_operational_changes ADD CONSTRAINT plan_operational_changes_actor_kind_check CHECK (actor_kind IN ('iam', 'system'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE plan_operational_changes ADD CONSTRAINT plan_operational_changes_actor_presence_check CHECK ((actor_kind = 'iam' AND actor_iam_id IS NOT NULL) OR (actor_kind = 'system' AND actor_iam_id IS NULL))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_operational_changes');
    }
};
