<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class NegativePnlRewardCalculationData extends Data
{
    public function __construct(public readonly string $signed_pnl, public readonly string $level_rate, public readonly string $personal_rate, public readonly bool $is_master, public readonly string $master_rate, public readonly string $currency_code, public readonly int $currency_precision, public readonly string $minimum_amount_major) {}
}
