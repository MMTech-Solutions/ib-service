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
        Schema::create('program_negative_pnl_configuration_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->string('cadence', 16);
            $table->uuid('actor_id');
            $table->uuid('closed_by_actor_id')->nullable();
            $table->timestampTz('starts_at', 6);
            $table->timestampTz('ends_at', 6)->nullable();
            $table->index(['program_id', 'starts_at']);
        });
        DB::statement('CREATE UNIQUE INDEX program_negative_pnl_active_unique ON program_negative_pnl_configuration_revisions (program_id) WHERE ends_at IS NULL');
        DB::statement("ALTER TABLE program_negative_pnl_configuration_revisions ADD CONSTRAINT program_negative_pnl_cadence_check CHECK (cadence IN ('daily','weekly','monthly','yearly')), ADD CONSTRAINT program_negative_pnl_interval_check CHECK (ends_at IS NULL OR ends_at >= starts_at)");
        Schema::create('program_negative_pnl_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('configuration_id')->constrained('program_negative_pnl_configuration_revisions')->restrictOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('server_group_id', 191);
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->jsonb('economic_context');
            $table->unique(['configuration_id', 'module_id', 'server_group_id'], 'program_negative_pnl_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_negative_pnl_groups');
        Schema::dropIfExists('program_negative_pnl_configuration_revisions');
    }
};
