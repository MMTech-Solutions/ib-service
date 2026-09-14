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
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['plan_id', 'code']);
            $table->unique(['plan_id', 'position']);
            $table->index(['plan_id', 'position']);
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE programs ADD CONSTRAINT programs_lock_version_check CHECK (lock_version > 0)'
            );
            app(ConnectionInterface::class)->statement(
                'ALTER TABLE programs ADD CONSTRAINT programs_position_check CHECK (position >= 1)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
