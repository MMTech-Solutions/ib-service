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
        Schema::create('cpa_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpa_context_id')->constrained('cpa_contexts')->restrictOnDelete();
            $table->string('source_key', 64);
            $table->foreignUuid('module_id')->nullable()->constrained('modules')->restrictOnDelete();
            $table->string('kind', 16);
            $table->jsonb('symbols_snapshot');
            $table->timestampTz('observed_until', 6)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('last_error_code', 64)->nullable();
            $table->timestampTz('last_evaluated_at', 6)->nullable();
            $table->unique(['cpa_context_id', 'source_key']);
            $table->index(['module_id', 'cpa_context_id']);
        });
        Schema::create('cpa_contributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpa_context_id')->constrained('cpa_contexts')->restrictOnDelete();
            $table->foreignUuid('cpa_source_id')->constrained('cpa_sources')->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('source_activity_id', 191);
            $table->uuid('subject_external_user_id');
            $table->string('kind', 16);
            $table->decimal('quantity', 32, 8);
            $table->string('unit_code', 16);
            $table->bigInteger('amount_minor')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('points_per_unit', 32, 8);
            $table->decimal('points', 64, 16);
            $table->string('instrument_reference', 200)->nullable();
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('verified_until', 6);
            $table->timestampTz('created_at', 6);
            $table->unique(['cpa_source_id', 'provider', 'source_activity_id']);
            $table->index(['cpa_context_id', 'kind']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX program_cpa_one_active ON program_cpa_rule_assignments(program_id) WHERE ends_at IS NULL');
            DB::statement('ALTER TABLE cpa_contributions ADD CONSTRAINT cpa_contribution_positive CHECK (quantity > 0 AND points_per_unit > 0 AND points > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS program_cpa_one_active');
        }
        Schema::dropIfExists('cpa_contributions');
        Schema::dropIfExists('cpa_sources');
    }
};
