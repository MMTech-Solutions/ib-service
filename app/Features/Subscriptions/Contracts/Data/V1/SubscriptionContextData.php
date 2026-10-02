<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class SubscriptionContextData extends Data
{
    public function __construct(
        public readonly string $subscription_id,
        public readonly string $plan_id,
        public readonly string $program_id,
        public readonly string $placement_id,
        public readonly string $placement_condition,
        public readonly string $activated_at = '',
        public readonly string $personal_rate = '1',
        public readonly bool $is_master = false,
        public readonly string $master_rate = '1',
    ) {}
}
