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
            $table->jsonb('requirements_snapshot');
            $table->uuid('reward_id')->nullable()->unique();
        });
        Schema::create('cpa_verification_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cpa_context_id')->unique()->constrained('cpa_contexts')->restrictOnDelete();
            $table->uuid('referred_user_id');
            $table->uuid('ib_user_id');
            $table->string('status', 16);
            $table->decimal('observed_volume_points', 64, 16)->default(0);
            $table->decimal('observed_deposit_points', 64, 16)->default(0);
            $table->bigInteger('observed_deposit_minor')->default(0);
            $table->boolean('volume_satisfied')->default(false);
            $table->boolean('deposit_satisfied')->default(false);
            $table->timestampTz('observed_from');
            $table->timestampTz('last_evaluated_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestampsTz();
            $table->index(['ib_user_id', 'status']);
            $table->index(['referred_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpa_verification_progress');
        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->dropUnique(['reward_id']);
            $table->dropColumn(['requirements_snapshot', 'reward_id']);
        });
    }
};
