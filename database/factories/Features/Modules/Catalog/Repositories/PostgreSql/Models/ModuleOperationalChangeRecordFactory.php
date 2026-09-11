<?php

declare(strict_types=1);

namespace Database\Factories\Features\Modules\Catalog\Repositories\PostgreSql\Models;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleOperationalChangeRecord;
use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ModuleOperationalChangeRecord> */
final class ModuleOperationalChangeRecordFactory extends Factory
{
    protected $model = ModuleOperationalChangeRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'module_id' => ModuleRecord::factory(),
            'action' => 'pause',
            'actor_iam_id' => (string) Str::uuid7(),
            'reason' => fake()->sentence(),
            'previous_is_active' => true,
            'previous_processing_status' => 'running',
            'next_is_active' => true,
            'next_processing_status' => 'paused',
            'occurred_at' => now('UTC'),
        ];
    }
}
