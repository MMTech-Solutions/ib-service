<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('progression_period', 16)->default('monthly');
        });

        DB::table('plans')
            ->whereNull('progression_period')
            ->update(['progression_period' => 'monthly']);

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE plans ALTER COLUMN progression_period SET NOT NULL'
            );
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE plans ALTER COLUMN progression_period DROP DEFAULT'
            );
            app(ConnectionInterface::class)->statement(
                "ALTER TABLE plans ADD CONSTRAINT plans_progression_period_check CHECK (progression_period IN ('daily', 'weekly', 'monthly'))"
            );
        }
    }

    public function down(): void
    {
        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE plans DROP CONSTRAINT IF EXISTS plans_progression_period_check'
            );
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('progression_period');
        });
    }
};
