<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 160)->primary();
            $table->jsonb('definition');
            $table->text('value')->nullable();
            $table->string('mode', 16);
            $table->unsignedInteger('lock_version');
            $table->timestampsTz();
        });
        Schema::create('setting_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 160)->index();
            $table->string('actor', 255);
            $table->string('action', 32);
            $table->string('reason', 1000);
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestampTz('occurred_at');
        });
        Schema::getConnection()->statement("ALTER TABLE settings ADD CONSTRAINT settings_mode_check CHECK (mode IN ('stored', 'fallback')), ADD CONSTRAINT settings_version_check CHECK (lock_version > 0), ADD CONSTRAINT settings_fallback_check CHECK (mode <> 'fallback' OR value IS NULL), ADD CONSTRAINT settings_definition_key_check CHECK (definition->>'key' = key)");
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_audits');
        Schema::dropIfExists('settings');
    }
};
