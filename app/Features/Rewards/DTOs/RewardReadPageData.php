<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class RewardReadPageData extends Data
{
    /** @param list<RewardReadData|NegativePnlJobReadData|NegativePnlPeriodReadData> $items */
    public function __construct(public readonly array $items, public readonly int $total, public readonly int $page, public readonly int $per_page) {}
}
