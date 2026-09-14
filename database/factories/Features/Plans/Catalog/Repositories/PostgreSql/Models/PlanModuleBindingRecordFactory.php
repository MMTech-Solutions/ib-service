<?php

declare(strict_types=1);

namespace Database\Factories\Features\Plans\Catalog\Repositories\PostgreSql\Models;

use App\Features\Modules\Catalog\Repositories\PostgreSql\Models\ModuleRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanModuleBindingRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlanModuleBindingRecord> */
final class PlanModuleBindingRecordFactory extends Factory
{
    protected $model = PlanModuleBindingRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'plan_id' => PlanRecord::factory(),
            'module_id' => ModuleRecord::factory(),
            'created_at' => now('UTC'),
        ];
    }
}
