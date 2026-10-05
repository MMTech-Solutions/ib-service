<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progression_run_results', function (Blueprint $table): void {
            $table->jsonb('original_decision')->nullable();
            $table->jsonb('recovery_attempts')->nullable();
        });
        DB::table('progression_run_results')->whereNotNull('decision_at')->orderBy('id')->each(function (object $row): void {
            DB::table('progression_run_results')->where('id', $row->id)->update(['original_decision' => json_encode([
                'total_points' => $row->total_points === null ? null : (string) $row->total_points,
                'target_program_id' => $row->target_program_id,
                'decision_at' => CarbonImmutable::parse($row->decision_at)->utc()->toISOString(),
            ], JSON_THROW_ON_ERROR)]);
        });
        Schema::table('progression_runs', fn (Blueprint $table) => $table->dropColumn('snapshot_generation'));
    }

    public function down(): void
    {
        Schema::table('progression_runs', fn (Blueprint $table) => $table->unsignedSmallInteger('snapshot_generation')->default(1));
        Schema::table('progression_run_results', fn (Blueprint $table) => $table->dropColumn(['original_decision', 'recovery_attempts']));
    }
};
