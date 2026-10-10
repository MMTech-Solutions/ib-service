<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionPlacementsQueryData extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $programId = null,
        public readonly ?bool $isFixed = null,
        public readonly ?string $overlapFrom = null,
        public readonly ?string $overlapUntil = null,
    ) {}
}
