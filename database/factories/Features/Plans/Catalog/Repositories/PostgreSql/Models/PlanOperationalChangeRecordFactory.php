<?php

declare(strict_types=1);

namespace Database\Factories\Features\Plans\Catalog\Repositories\PostgreSql\Models;

use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanOperationalChangeRecord;
use App\Features\Plans\Catalog\Repositories\PostgreSql\Models\PlanRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlanOperationalChangeRecord> */
final class PlanOperationalChangeRecordFactory extends Factory
{
    protected $model = PlanOperationalChangeRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'plan_id' => PlanRecord::factory(),
            'action' => 'activate',
            'actor_kind' => 'iam',
            'actor_iam_id' => (string) Str::uuid7(),
            'reason' => 'Operational reason',
            'previous_is_active' => false,
            'next_is_active' => true,
            'cause_event_id' => null,
            'cause_module_id' => null,
            'initiating_actor_iam_id' => null,
            'occurred_at' => now('UTC'),
        ];
    }
}
