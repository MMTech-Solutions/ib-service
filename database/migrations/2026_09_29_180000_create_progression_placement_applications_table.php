<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progression_placement_applications', function (Blueprint $table): void {
            $table->foreignUuid('run_result_id')->primary()->constrained('progression_run_results')->restrictOnDelete();
            $table->boolean('changed');
            $table->timestampTz('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progression_placement_applications');
    }
};
