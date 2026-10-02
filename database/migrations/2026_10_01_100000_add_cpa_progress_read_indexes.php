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
            $table->index(['captured_at', 'id'], 'cpa_contexts_captured_at_id_index');
            $table->index(['program_id', 'captured_at', 'id'], 'cpa_contexts_program_captured_index');
            $table->index(['module_id', 'captured_at', 'id'], 'cpa_contexts_module_captured_index');
        });
    }

    public function down(): void
    {
        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->dropIndex('cpa_contexts_captured_at_id_index');
            $table->dropIndex('cpa_contexts_program_captured_index');
            $table->dropIndex('cpa_contexts_module_captured_index');
        });
    }
};
