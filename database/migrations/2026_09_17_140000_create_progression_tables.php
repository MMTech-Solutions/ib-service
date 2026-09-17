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
        Schema::create('progression_activity_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('source_activity_id', 191);
            $table->uuid('beneficiary_external_user_id');
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->restrictOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->foreignUuid('program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('window_starts_at')->nullable();
            $table->timestampTz('window_ends_at')->nullable();
            $table->string('metric_code', 64);
            $table->string('unit_code', 64);
            $table->string('instrument_reference', 191)->nullable();
            $table->decimal('quantity', 24, 8);
            $table->string('status', 16);
            $table->string('exclusion_reason', 64)->nullable();
            $table->timestampTz('evaluated_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(
                ['module_id', 'source_activity_id', 'beneficiary_external_user_id'],
                'progression_evaluations_idempotency_unique',
            );
            $table->index(
                ['subscription_id', 'occurred_at', 'id'],
                'progression_evaluations_subscription_occurred_idx',
            );
            $table->index(
                ['plan_id', 'occurred_at', 'id'],
                'progression_evaluations_plan_occurred_idx',
            );
            $table->index(
                ['status', 'exclusion_reason', 'evaluated_at', 'id'],
                'progression_evaluations_status_reason_idx',
            );
        });

        Schema::create('progression_contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')
                ->unique()
                ->constrained('progression_activity_evaluations')
                ->restrictOnDelete();
            $table->foreignUuid('rule_id')->constrained('rules')->restrictOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->foreignUuid('rule_assignment_id')->constrained('rule_assignments')->restrictOnDelete();
            $table->string('strategy_type', 64);
            $table->string('scope_type', 16);
            $table->decimal('weight', 24, 8);
            $table->decimal('points', 24, 8);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['rule_version_id'], 'progression_contributions_rule_version_idx');
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            $connection = app(ConnectionInterface::class);

            $connection->statement(
                "ALTER TABLE progression_activity_evaluations
                 ADD CONSTRAINT progression_evaluations_status_check
                 CHECK (status IN ('accepted', 'excluded'))"
            );
            $connection->statement(
                "ALTER TABLE progression_activity_evaluations
                 ADD CONSTRAINT progression_evaluations_exclusion_reason_check
                 CHECK (
                    exclusion_reason IS NULL
                    OR exclusion_reason IN (
                        'placement_fixed',
                        'plan_inactive',
                        'module_not_selected',
                        'module_inactive',
                        'unit_mismatch',
                        'scale_exceeded',
                        'window_closed_after_pause',
                        'late_activity',
                        'no_active_subscription',
                        'no_applicable_rule'
                    )
                 )"
            );
            $connection->statement(
                "ALTER TABLE progression_activity_evaluations
                 ADD CONSTRAINT progression_evaluations_status_reason_shape_check
                 CHECK (
                    (status = 'accepted' AND exclusion_reason IS NULL)
                    OR (status = 'excluded' AND exclusion_reason IS NOT NULL)
                 )"
            );
            $connection->statement(
                "ALTER TABLE progression_activity_evaluations
                 ADD CONSTRAINT progression_evaluations_accepted_context_check
                 CHECK (
                    status = 'excluded'
                    OR (
                        subscription_id IS NOT NULL
                        AND plan_id IS NOT NULL
                        AND program_id IS NOT NULL
                        AND window_starts_at IS NOT NULL
                        AND window_ends_at IS NOT NULL
                    )
                 )"
            );
            $connection->statement(
                'ALTER TABLE progression_activity_evaluations
                 ADD CONSTRAINT progression_evaluations_window_order_check
                 CHECK (
                    window_starts_at IS NULL
                    OR window_ends_at IS NULL
                    OR window_ends_at > window_starts_at
                 )'
            );
            $connection->statement(
                "ALTER TABLE progression_contributions
                 ADD CONSTRAINT progression_contributions_strategy_type_check
                 CHECK (strategy_type = 'points_per_quantity_unit')"
            );
            $connection->statement(
                "ALTER TABLE progression_contributions
                 ADD CONSTRAINT progression_contributions_scope_type_check
                 CHECK (scope_type = 'all')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_contributions');
        Schema::dropIfExists('progression_activity_evaluations');
    }
};
