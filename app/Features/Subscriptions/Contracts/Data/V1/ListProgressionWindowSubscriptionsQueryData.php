<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ListProgressionWindowSubscriptionsQueryData extends Data
{
    public function __construct(
        public readonly string $plan_id,
        public readonly string $window_starts_at,
        public readonly string $window_ends_at,
    ) {}
}
