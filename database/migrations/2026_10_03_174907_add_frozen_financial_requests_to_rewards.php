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
        Schema::table('rewards', function (Blueprint $table) {
            Schema::table('rewards', function (Blueprint $table): void {
                $table->jsonb('settlement_request_snapshot')->nullable();
            });
            Schema::table('reward_financial_operations', function (Blueprint $table): void {
                $table->jsonb('request_snapshot')->nullable();
                $table->string('outcome', 64)->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            Schema::table('reward_financial_operations', function (Blueprint $table): void {
                $table->dropColumn(['request_snapshot', 'outcome']);
            });
            Schema::table('rewards', function (Blueprint $table): void {
                $table->dropColumn('settlement_request_snapshot');
            });
        });
    }
};
