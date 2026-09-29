<?php

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
        Schema::table('progression_activity_evaluations', function (Blueprint $table) {
            $table->foreignUuid('activity_distribution_id')->nullable()->after('source_activity_id')->constrained('progression_activity_distributions')->restrictOnDelete();
            $table->uuid('source_external_user_id')->nullable()->after('activity_distribution_id');
            $table->unsignedInteger('distribution_level')->default(0)->after('source_external_user_id');
            $table->timestampTz('distribution_resolved_at')->nullable()->after('distribution_level');
            $table->index(['activity_distribution_id', 'distribution_level'], 'progression_evaluations_distribution_level_idx');
            $table->dropUnique('progression_evaluations_idempotency_unique');
            $table->unique(['module_id', 'source_activity_id', 'beneficiary_external_user_id', 'distribution_level'], 'progression_evaluations_network_idempotency_unique');
        });

        Schema::table('progression_contributions', function (Blueprint $table) {
            $table->foreignUuid('program_symbol_configuration_id')->nullable()->after('rule_assignment_id')->constrained('program_symbol_configurations')->restrictOnDelete();
            $table->foreignUuid('plan_progression_template_version_binding_id')->nullable()->after('program_symbol_configuration_id')->constrained('plan_progression_template_version_bindings')->restrictOnDelete();
            $table->foreignUuid('progression_template_version_id')->nullable()->after('plan_progression_template_version_binding_id')->constrained('progression_template_versions')->restrictOnDelete();
            $table->decimal('distribution_weight', 24, 8)->default(1)->after('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progression_contributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_symbol_configuration_id');
            $table->dropConstrainedForeignId('plan_progression_template_version_binding_id');
            $table->dropConstrainedForeignId('progression_template_version_id');
            $table->dropColumn('distribution_weight');
        });

        Schema::table('progression_activity_evaluations', function (Blueprint $table) {
            $table->dropUnique('progression_evaluations_network_idempotency_unique');
            $table->unique(['module_id', 'source_activity_id', 'beneficiary_external_user_id'], 'progression_evaluations_idempotency_unique');
            $table->dropIndex('progression_evaluations_distribution_level_idx');
            $table->dropConstrainedForeignId('activity_distribution_id');
            $table->dropColumn(['source_external_user_id', 'distribution_level', 'distribution_resolved_at']);
        });
    }
};
