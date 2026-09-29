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
        Schema::create('progression_activity_distributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('source_activity_id', 191);
            $table->uuid('source_external_user_id');
            $table->timestampTz('resolved_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['module_id', 'source_activity_id'], 'progression_distributions_source_unique');
            $table->index('source_external_user_id', 'progression_distributions_source_user_idx');
        });
        Schema::create('progression_activity_distribution_beneficiaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('distribution_id')->constrained('progression_activity_distributions')->restrictOnDelete();
            $table->uuid('beneficiary_external_user_id');
            $table->unsignedInteger('distribution_level');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['distribution_id', 'beneficiary_external_user_id', 'distribution_level'], 'progression_distribution_beneficiaries_unique');
            $table->index('beneficiary_external_user_id', 'progression_distribution_beneficiary_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progression_activity_distribution_beneficiaries');
        Schema::dropIfExists('progression_activity_distributions');
    }
};
