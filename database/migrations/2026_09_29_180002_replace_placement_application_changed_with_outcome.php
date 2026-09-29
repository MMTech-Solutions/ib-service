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
        Schema::table('progression_placement_applications', function (Blueprint $table): void {
            $table->string('outcome', 16)->nullable()->after('run_result_id');
        });

        $connection = app(ConnectionInterface::class);
        $connection->table('progression_placement_applications')->where('changed', true)->update(['outcome' => 'applied']);
        $connection->table('progression_placement_applications')->where('changed', false)->update(['outcome' => 'unchanged']);

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('ALTER TABLE progression_placement_applications ALTER COLUMN outcome SET NOT NULL');
            $connection->statement("ALTER TABLE progression_placement_applications ADD CONSTRAINT progression_placement_applications_outcome_check CHECK (outcome IN ('applied', 'unchanged', 'fixed', 'not_active'))");
        }

        Schema::table('progression_placement_applications', function (Blueprint $table): void {
            $table->dropColumn('changed');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rolling back placement application outcomes would discard audit meaning.');
    }
};
