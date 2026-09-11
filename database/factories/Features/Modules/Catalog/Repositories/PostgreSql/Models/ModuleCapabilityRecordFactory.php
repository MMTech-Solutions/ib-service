<?php

declare(strict_types=1);

namespace Database\Factories\Features\Modules\Catalog\Repositories\PostgreSql\Models;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleCapabilityRecord;
use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ModuleCapabilityRecord> */
final class ModuleCapabilityRecordFactory extends Factory
{
    protected $model = ModuleCapabilityRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'module_id' => ModuleRecord::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
