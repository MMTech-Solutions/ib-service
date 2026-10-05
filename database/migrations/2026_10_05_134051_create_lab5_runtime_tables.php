<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progression_lab_failures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_id');
            $table->string('operation', 16);
            $table->string('stage', 32);
            $table->string('selector_type', 16);
            $table->uuid('selector_id');
            $table->timestampTz('consumed_at')->nullable();
            $table->index(['context_id', 'operation', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_lab_failures');
    }
};
