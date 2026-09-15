<?php

declare(strict_types=1);

namespace Database\Factories\Features\Programs\Catalog\Repositories\PostgreSql\Models;

use App\Features\Programs\Catalog\Repositories\PostgreSql\Models\ProgramRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProgramRecord> */
final class ProgramRecordFactory extends Factory
{
    protected $model = ProgramRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $now = now('UTC');

        return [
            'id' => (string) Str::uuid7(),
            'plan_id' => (string) Str::uuid7(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'position' => 1,
            'entry_threshold' => 0,
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
