<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveNegativePnlPeriodsResultData extends Data
{
    /** @param list<NegativePnlPeriodData> $periods */
    public function __construct(public readonly array $periods) {}
}
