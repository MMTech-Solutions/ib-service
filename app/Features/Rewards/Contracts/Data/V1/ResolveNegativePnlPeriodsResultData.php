<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveNegativePnlPeriodsResultData extends Data
{
    /** @param list<NegativePnlPeriodData> $periods @param list<string> $completed_subjects */
    public function __construct(public readonly array $periods, public readonly array $completed_subjects) {}
}
