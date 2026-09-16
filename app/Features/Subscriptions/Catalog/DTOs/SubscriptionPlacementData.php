<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionPlacementData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $program_id,
        public readonly bool $is_fixed,
        public readonly string $effective_from,
        public readonly ?string $effective_until,
    ) {}
}
