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
        Schema::create('rule_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->constrained('rules')->restrictOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->restrictOnDelete();
            $table->foreignUuid('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignUuid('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('scope_type', 16)->default('all');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->index(['rule_id', 'starts_at', 'id']);
            $table->index(['rule_id', 'program_id']);
            $table->index(['rule_id', 'module_id']);
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            $connection = app(ConnectionInterface::class);
            $connection->statement(
                'ALTER TABLE rule_assignments ADD CONSTRAINT rule_assignments_lock_version_check CHECK (lock_version > 0)'
            );
            $connection->statement(
                "ALTER TABLE rule_assignments ADD CONSTRAINT rule_assignments_scope_type_check CHECK (scope_type = 'all')"
            );
            $connection->statement(
                'ALTER TABLE rule_assignments ADD CONSTRAINT rule_assignments_ends_after_starts_check CHECK (ends_at IS NULL OR ends_at >= starts_at)'
            );
            $connection->statement(
                'CREATE UNIQUE INDEX rule_assignments_active_unique ON rule_assignments (rule_id, program_id, module_id) WHERE ends_at IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_assignments');
    }
};
