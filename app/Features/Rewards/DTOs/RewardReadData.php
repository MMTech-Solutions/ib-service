<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class RewardReadData extends Data
{
    /** @param array<string, mixed>|null $audit */
    public function __construct(public readonly string $id, public readonly string $status, public readonly string $commission_type, public readonly int $amount_minor, public readonly string $currency_code, public readonly int $currency_precision, public readonly string $plan_id, public readonly string $program_id, public readonly string $module_id, public readonly string $created_at, public readonly ?string $settled_at, public readonly ?string $compensates_reward_id, public readonly ?array $audit) {}
}
