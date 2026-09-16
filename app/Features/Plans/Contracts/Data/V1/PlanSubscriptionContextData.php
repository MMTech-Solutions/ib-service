<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class PlanSubscriptionContextData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly bool $is_active,
        public readonly bool $archived,
        public readonly bool $requires_approval,
    ) {}
}
