<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module_operational_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('action', 16);
            $table->uuid('actor_iam_id');
            $table->string('reason', 500);
            $table->boolean('previous_is_active');
            $table->string('previous_processing_status', 16);
            $table->boolean('next_is_active');
            $table->string('next_processing_status', 16);
            $table->timestampTz('occurred_at');
            $table->index(['module_id', 'occurred_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE module_operational_changes ADD CONSTRAINT module_operational_changes_action_check CHECK (action IN ('activate', 'deactivate', 'pause', 'resume'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE module_operational_changes ADD CONSTRAINT module_operational_changes_previous_processing_status_check CHECK (previous_processing_status IN ('running', 'paused'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE module_operational_changes ADD CONSTRAINT module_operational_changes_next_processing_status_check CHECK (next_processing_status IN ('running', 'paused'))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_operational_changes');
    }
};
