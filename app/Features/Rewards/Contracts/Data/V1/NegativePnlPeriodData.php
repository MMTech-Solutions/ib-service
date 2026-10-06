<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlPeriodData extends Data
{
    /** @param list<string> $position_ids */
    public function __construct(
        public readonly string $trading_account_id,
        public readonly string $external_trader_id,
        public readonly string $external_user_id,
        public readonly string $server_group_id,
        public readonly string $currency_code,
        public readonly int $currency_precision,
        public readonly string $occurred_from,
        public readonly string $occurred_until,
        public readonly string $npnl,
        public readonly array $position_ids,
    ) {}
}
