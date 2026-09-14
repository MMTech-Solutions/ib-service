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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();
            $table->index(['deleted_at', 'is_active', 'code']);
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE plans ADD CONSTRAINT plans_lock_version_check CHECK (lock_version > 0)'
            );
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE plans ADD CONSTRAINT plans_archived_inactive_check CHECK (deleted_at IS NULL OR is_active = false)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
