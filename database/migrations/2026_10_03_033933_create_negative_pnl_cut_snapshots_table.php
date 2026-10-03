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
        Schema::create('negative_pnl_cut_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('identity_key', 64)->unique();
            $table->uuid('module_id');
            $table->uuid('subscription_id');
            $table->string('account_id', 191);
            $table->string('server_group_id', 191);
            $table->string('cadence', 16);
            $table->timestampTz('occurred_from', 6)->nullable();
            $table->timestampTz('occurred_until', 6);
            $table->jsonb('snapshot');
            $table->index(['subscription_id', 'occurred_until']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negative_pnl_cut_snapshots');
    }
};
