<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Rewards\Contracts\Data\V1\NegativePnlPeriodData;
use Spatie\LaravelData\Data;

final class NegativePnlAggregateData extends Data
{
    /** @param list<NegativePnlPeriodData> $contributions */
    public function __construct(public readonly int $distribution_level, public readonly string $currency_code, public readonly int $currency_precision, public string $signed_pnl = '0', public array $contributions = []) {}
}
