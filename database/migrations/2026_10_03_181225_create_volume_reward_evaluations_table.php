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
        Schema::create('volume_reward_evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('source_activity_id', 191);
            $table->jsonb('activity');
            $table->jsonb('distribution')->nullable();
            $table->jsonb('preparations')->default('{}');
            $table->jsonb('outcomes')->default('{}');
            $table->uuid('lease_token')->nullable();
            $table->timestampTz('lease_expires_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['module_id', 'source_activity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volume_reward_evaluations');
    }
};
