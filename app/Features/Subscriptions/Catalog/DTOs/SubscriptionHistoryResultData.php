<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionHistoryResultData extends Data
{
    /** @param list<SubscriptionChangeHistoryData|SubscriptionPlacementHistoryData> $entries */
    public function __construct(public readonly array $entries, public readonly int $total) {}
}
