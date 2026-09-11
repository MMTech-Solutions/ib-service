<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
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
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('processing_status', 16)->default('running');
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['is_active', 'processing_status', 'code']);
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement(
                "ALTER TABLE modules ADD CONSTRAINT modules_processing_status_check CHECK (processing_status IN ('running', 'paused'))"
            );
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE modules ADD CONSTRAINT modules_lock_version_check CHECK (lock_version > 0)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
