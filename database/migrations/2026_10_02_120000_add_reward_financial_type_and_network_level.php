<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table): void {
            $table->string('commission_type', 16)->default('cpa')->after('status');
            $table->unsignedSmallInteger('network_level')->default(1)->after('commission_type');
            $table->index(['commission_type', 'status'], 'rewards_financial_type_selection_index');
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement("ALTER TABLE rewards ADD CONSTRAINT rewards_commission_type_check CHECK (commission_type IN ('cpa', 'volume', 'pnl', 'adjustment'))");
        }
    }

    public function down(): void
    {
        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement('ALTER TABLE rewards DROP CONSTRAINT IF EXISTS rewards_commission_type_check');
        }

        Schema::table('rewards', function (Blueprint $table): void {
            $table->dropIndex('rewards_financial_type_selection_index');
            $table->dropColumn(['commission_type', 'network_level']);
        });
    }
};
