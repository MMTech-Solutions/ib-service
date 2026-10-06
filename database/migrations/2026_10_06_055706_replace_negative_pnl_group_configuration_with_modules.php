<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('negative_pnl_periods')->where('status', '!=', 'completed')->exists()) {
            throw new RuntimeException('Complete all open PnL periods with the previous release before upgrading.');
        }
        $converted = [];
        foreach (DB::table('program_negative_pnl_groups')->orderBy('id')->get() as $row) {
            $context = json_decode($row->economic_context, true, 512, JSON_THROW_ON_ERROR);
            unset($context['server_group_id']);
            $key = $row->configuration_id.':'.$row->module_id;
            if (isset($converted[$key]) && $converted[$key]['economic_context'] !== json_encode($context, JSON_THROW_ON_ERROR)) {
                throw new RuntimeException('Divergent PnL selections: explicitly select a common rule for configuration '.$row->configuration_id.' before upgrading.');
            }
            $converted[$key] = ['id' => $row->id, 'configuration_id' => $row->configuration_id, 'module_id' => $row->module_id, 'rule_version_id' => $row->rule_version_id, 'economic_context' => json_encode($context, JSON_THROW_ON_ERROR)];
        }
        $jobs = [];
        foreach (DB::table('negative_pnl_jobs')->whereNotNull('server_group_id')->get() as $job) {
            $key = hash('sha256', json_encode([$job->subscription_id, $job->module_id, $job->cadence], JSON_THROW_ON_ERROR));
            if (isset($jobs[$key]) && [$jobs[$key][0]->cursor_at, $jobs[$key][0]->next_cut_at, $jobs[$key][0]->closed_at, $jobs[$key][0]->reset_baseline, $jobs[$key][0]->finished_at === null] !== [$job->cursor_at, $job->next_cut_at, $job->closed_at, $job->reset_baseline, $job->finished_at === null]) {
                throw new RuntimeException('Align PnL cursors for subscription '.$job->subscription_id.' before upgrading.');
            }
            $jobs[$key][] = $job;
        }
        Schema::create('program_negative_pnl_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('configuration_id')->constrained('program_negative_pnl_configuration_revisions')->restrictOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->jsonb('economic_context');
            $table->unique(['configuration_id', 'module_id']);
        });
        foreach ($converted as $row) {
            DB::table('program_negative_pnl_modules')->insert($row);
        }
        Schema::table('negative_pnl_jobs', function (Blueprint $table): void {
            $table->string('server_group_id', 191)->nullable()->change();
        });
        foreach ($jobs as $key => $sources) {
            $merged = (array) $sources[0];
            $merged['id'] = (string) Str::uuid7();
            $merged['identity_key'] = $key;
            $merged['server_group_id'] = null;
            $merged['lease_token'] = null;
            $merged['lease_expires_at'] = null;
            $merged['retry_at'] = null;
            $merged['error_code'] = null;
            DB::table('negative_pnl_jobs')->insert($merged);
            $seen = [];
            foreach ($sources as $source) {
                foreach (DB::table('negative_pnl_baselines')->where('job_id', $source->id)->get() as $baseline) {
                    $identity = $baseline->referral_id.':'.$baseline->account_id;
                    if (isset($seen[$identity])) {
                        throw new RuntimeException('Duplicate account baselines during PnL consolidation.');
                    }
                    $seen[$identity] = true;
                    $row = (array) $baseline;
                    $row['id'] = (string) Str::uuid7();
                    $row['job_id'] = $merged['id'];
                    DB::table('negative_pnl_baselines')->insert($row);
                }
            }
        }
        DB::table('negative_pnl_jobs')->whereNotNull('server_group_id')->whereNull('finished_at')->update(['finished_at' => now('UTC'), 'lease_token' => null, 'lease_expires_at' => null]);
    }

    public function down(): void
    {
        if (DB::table('negative_pnl_jobs')->whereNull('server_group_id')->exists()) {
            throw new RuntimeException('PnL aggregate jobs cannot be downgraded to per-group processing.');
        }
        Schema::dropIfExists('program_negative_pnl_modules');
        Schema::table('negative_pnl_jobs', function (Blueprint $table): void {
            $table->string('server_group_id', 191)->nullable(false)->change();
        });
    }
};
