<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $connection = app(ConnectionInterface::class);
        $connection->statement('UPDATE programs SET entry_threshold = 0 WHERE position = 1 AND entry_threshold <> 0');

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(
                'ALTER TABLE programs ADD CONSTRAINT programs_first_entry_threshold_check CHECK (position <> 1 OR entry_threshold = 0)'
            );
        }
    }

    public function down(): void
    {
        $connection = app(ConnectionInterface::class);
        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('ALTER TABLE programs DROP CONSTRAINT IF EXISTS programs_first_entry_threshold_check');
        }
    }
};
