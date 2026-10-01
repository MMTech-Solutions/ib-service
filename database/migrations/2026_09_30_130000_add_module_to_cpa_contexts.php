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
            $table->foreignUuid('module_id')->nullable()->after('program_id')->constrained('modules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cpa_contexts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('module_id');
        });
    }
};
