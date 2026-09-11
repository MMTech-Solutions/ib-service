<?php

declare(strict_types=1);

namespace Database\Factories\Features\Modules\Catalog\Repositories\PostgreSql\Models;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ModuleRecord> */
final class ModuleRecordFactory extends Factory
{
    protected $model = ModuleRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'processing_status' => 'running',
            'lock_version' => 1,
        ];
    }
}
