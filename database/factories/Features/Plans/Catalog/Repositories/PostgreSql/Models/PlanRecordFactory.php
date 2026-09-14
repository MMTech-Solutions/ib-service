<?php

declare(strict_types=1);

namespace Database\Factories\Features\Plans\Catalog\Repositories\PostgreSql\Models;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlanRecord> */
final class PlanRecordFactory extends Factory
{
    protected $model = PlanRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $now = now('UTC');

        return [
            'id' => (string) Str::uuid7(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => false,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
    }
}
