<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->foreignUuid('rule_assignment_id')->nullable()->after('module_id')->constrained('rule_assignments')->restrictOnDelete();
        });

        Schema::create('rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('beneficiary_user_id');
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->foreignUuid('rule_assignment_id')->constrained('rule_assignments')->restrictOnDelete();
            $table->foreignUuid('rule_id')->constrained('rules')->restrictOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->unsignedSmallInteger('currency_precision');
            $table->string('status', 16);
            $table->jsonb('summary_snapshot');
            $table->timestampsTz();
            $table->index(['beneficiary_user_id', 'status']);
        });

        Schema::create('reward_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reward_id')->constrained('rewards')->cascadeOnDelete();
            $table->string('evidence_provider', 64);
            $table->string('evidence_type', 64);
            $table->string('source_activity_id', 191);
            $table->uuid('subject_external_user_id')->nullable();
            $table->string('quantity', 24)->nullable();
            $table->string('unit_code', 16)->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->string('instrument_reference', 200)->nullable();
            $table->timestampsTz();
            $table->unique(['reward_id', 'evidence_provider', 'source_activity_id']);
        });

        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->foreign('reward_id')->references('id')->on('rewards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->dropForeign(['reward_id']);
        });
        Schema::dropIfExists('reward_evidence');
        Schema::dropIfExists('rewards');
        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rule_assignment_id');
        });
    }
};
