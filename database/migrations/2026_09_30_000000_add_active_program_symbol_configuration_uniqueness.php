<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX program_symbol_configurations_active_unique ON program_symbol_configurations (program_id, module_id, symbol_reference, server_group_reference) WHERE ends_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS program_symbol_configurations_active_unique');
    }
};
