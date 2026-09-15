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
        Schema::create('rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 64);
            $table->text('description')->nullable();
            $table->string('strategy_type', 64);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['plan_id', 'name']);
            $table->unique(['plan_id', 'slug']);
            $table->index(['plan_id', 'name']);
        });

        Schema::create('rule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->constrained('rules')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16);
            $table->unsignedInteger('schema_version');
            $table->jsonb('configuration');
            $table->timestampTz('published_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->unique(['rule_id', 'version_number']);
            $table->index(['rule_id', 'version_number']);
        });

        if (app(ConnectionInterface::class)->getDriverName() === 'pgsql') {
            $connection = app(ConnectionInterface::class);
            $connection->statement(
                'ALTER TABLE rules ADD CONSTRAINT rules_lock_version_check CHECK (lock_version > 0)'
            );
            $connection->statement(
                'ALTER TABLE rule_versions ADD CONSTRAINT rule_versions_lock_version_check CHECK (lock_version > 0)'
            );
            $connection->statement(
                'ALTER TABLE rule_versions ADD CONSTRAINT rule_versions_version_number_check CHECK (version_number >= 1)'
            );
            $connection->statement(
                'ALTER TABLE rule_versions ADD CONSTRAINT rule_versions_schema_version_check CHECK (schema_version >= 1)'
            );
            $connection->statement(
                "ALTER TABLE rule_versions ADD CONSTRAINT rule_versions_status_check CHECK (status IN ('draft', 'published'))"
            );
            $connection->statement(
                "ALTER TABLE rule_versions ADD CONSTRAINT rule_versions_published_at_check CHECK ((status = 'draft' AND published_at IS NULL) OR (status = 'published' AND published_at IS NOT NULL))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_versions');
        Schema::dropIfExists('rules');
    }
};
