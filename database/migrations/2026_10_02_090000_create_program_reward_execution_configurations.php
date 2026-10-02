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
        Schema::create('program_volume_reward_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->string('trigger_mode', 16);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
            $table->index(['program_id', 'starts_at']);
        });

        Schema::create('program_negative_pnl_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('period_frequency', 16);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
            $table->index(['program_id', 'module_id', 'starts_at']);
        });

        DB::statement('CREATE UNIQUE INDEX program_volume_reward_configurations_active_unique ON program_volume_reward_configurations (program_id) WHERE ends_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX program_negative_pnl_configurations_active_unique ON program_negative_pnl_configurations (program_id, module_id) WHERE ends_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS program_negative_pnl_configurations_active_unique');
        DB::statement('DROP INDEX IF EXISTS program_volume_reward_configurations_active_unique');
        Schema::dropIfExists('program_negative_pnl_configurations');
        Schema::dropIfExists('program_volume_reward_configurations');
    }
};
