<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionsPageData extends Data
{
    /**
     * @param  list<SubscriptionData>  $subscriptions
     */
    public function __construct(
        public readonly array $subscriptions,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
