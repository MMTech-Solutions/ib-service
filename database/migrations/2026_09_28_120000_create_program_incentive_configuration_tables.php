<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progression_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique('name');
        });
        Schema::create('progression_template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('progression_templates')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['template_id', 'version_number']);
        });
        Schema::create('progression_template_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_version_id')->constrained('progression_template_versions')->restrictOnDelete();
            $table->unsignedInteger('distribution_level');
            $table->decimal('weight', 20, 8);
            $table->unique(['template_version_id', 'distribution_level']);
        });
        Schema::create('plan_progression_template_version_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignUuid('template_version_id')->constrained('progression_template_versions')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['plan_id', 'template_version_id']);
        });

        Schema::create('payment_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique('name');
        });
        Schema::create('payment_template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('payment_templates')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['template_id', 'version_number']);
        });
        Schema::create('payment_template_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_version_id')->constrained('payment_template_versions')->restrictOnDelete();
            $table->unsignedInteger('distribution_level');
            $table->decimal('rate', 20, 8);
            $table->unique(['template_version_id', 'distribution_level']);
        });
        Schema::create('plan_payment_template_version_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignUuid('template_version_id')->constrained('payment_template_versions')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->unique(['plan_id', 'template_version_id']);
        });

        Schema::create('program_symbol_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('symbol_reference', 160);
            $table->string('server_group_reference', 160);
            $table->string('currency_code', 3);
            $table->boolean('use_for_progression')->default(false);
            $table->foreignUuid('plan_progression_template_version_binding_id')->nullable()->constrained('plan_progression_template_version_bindings')->restrictOnDelete();
            $table->boolean('use_for_volume_reward')->default(false);
            $table->foreignUuid('plan_payment_template_version_binding_id')->nullable()->constrained('plan_payment_template_version_bindings')->restrictOnDelete();
            $table->string('commission_type', 16)->nullable();
            $table->decimal('commission_value', 20, 8)->nullable();
            $table->boolean('use_for_cpa')->default(false);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['program_id', 'symbol_reference', 'starts_at']);
        });

        Schema::create('program_cpa_rule_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUuid('rule_id')->constrained('rules')->restrictOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['program_id', 'starts_at']);
        });
        Schema::create('cpa_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referred_user_id');
            $table->uuid('ib_user_id');
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUuid('rule_id')->constrained('rules')->restrictOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->jsonb('symbols_snapshot');
            $table->timestampTz('captured_at');
            $table->unique(['referred_user_id', 'ib_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpa_contexts');
        Schema::dropIfExists('program_cpa_rule_assignments');
        Schema::dropIfExists('program_symbol_configurations');
        Schema::dropIfExists('plan_payment_template_version_bindings');
        Schema::dropIfExists('payment_template_levels');
        Schema::dropIfExists('payment_template_versions');
        Schema::dropIfExists('payment_templates');
        Schema::dropIfExists('progression_template_levels');
        Schema::dropIfExists('plan_progression_template_version_bindings');
        Schema::dropIfExists('progression_template_versions');
        Schema::dropIfExists('progression_templates');
    }
};
