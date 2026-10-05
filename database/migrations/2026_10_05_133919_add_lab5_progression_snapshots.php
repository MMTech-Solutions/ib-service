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
        Schema::table('progression_runs', function (Blueprint $table): void {
            $table->jsonb('snapshot')->nullable();
            $table->unsignedSmallInteger('snapshot_generation')->default(1);
        });
        DB::table('progression_runs')->update(['snapshot_generation' => 0]);
        Schema::table('progression_run_results', function (Blueprint $table): void {
            $table->timestampTz('decision_at')->nullable();
            $table->unsignedInteger('placement_attempt_count')->default(0);
            $table->string('placement_failure_code', 64)->nullable();
            $table->timestampTz('placement_last_attempt_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('progression_run_results', fn (Blueprint $table) => $table->dropColumn(['decision_at', 'placement_attempt_count', 'placement_failure_code', 'placement_last_attempt_at']));
        Schema::table('progression_runs', fn (Blueprint $table) => $table->dropColumn(['snapshot', 'snapshot_generation']));
    }
};
