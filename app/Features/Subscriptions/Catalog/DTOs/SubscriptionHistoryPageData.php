<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use App\Features\Subscriptions\Catalog\Models\SubscriptionChange;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use Spatie\LaravelData\Data;

final class SubscriptionHistoryPageData extends Data
{
    /** @param list<SubscriptionChange|SubscriptionPlacement> $entries */
    public function __construct(public readonly array $entries, public readonly int $total) {}
}
