<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlBaselineData extends Data
{
    public function __construct(
        public readonly string $account_id,
        public readonly string $balance_after,
        public readonly string $occurred_until,
    ) {}
}
