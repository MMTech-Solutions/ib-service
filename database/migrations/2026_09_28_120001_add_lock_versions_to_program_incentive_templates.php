<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_templates', 'payment_template_versions', 'progression_templates', 'progression_template_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_templates', 'payment_template_versions', 'progression_templates', 'progression_template_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }
    }
};
