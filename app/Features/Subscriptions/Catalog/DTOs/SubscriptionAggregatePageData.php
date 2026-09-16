<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use App\Features\Subscriptions\Catalog\Models\Subscription;

final class SubscriptionAggregatePageData
{
    /**
     * @param  list<Subscription>  $subscriptions
     */
    public function __construct(
        public readonly array $subscriptions,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
