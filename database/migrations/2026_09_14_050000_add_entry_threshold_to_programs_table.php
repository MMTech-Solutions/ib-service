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
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedInteger('entry_threshold')->default(0);
        });

        $connection = app(ConnectionInterface::class);
        $connection->statement('UPDATE programs SET entry_threshold = position - 1');

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(
                'ALTER TABLE programs ADD CONSTRAINT programs_entry_threshold_check CHECK (entry_threshold >= 0)'
            );
        }
    }

    public function down(): void
    {
        $connection = app(ConnectionInterface::class);
        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('ALTER TABLE programs DROP CONSTRAINT IF EXISTS programs_entry_threshold_check');
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('entry_threshold');
        });
    }
};
