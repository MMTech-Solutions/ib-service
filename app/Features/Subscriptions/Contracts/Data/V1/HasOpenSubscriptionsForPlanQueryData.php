<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class HasOpenSubscriptionsForPlanQueryData extends Data
{
    public function __construct(
        public readonly string $plan_id,
    ) {}
}
