<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveNegativePnlPeriodsQueryData extends Data
{
    /** @param list<NegativePnlSubjectData> $subjects */
    public function __construct(public readonly array $subjects, public readonly string $occurred_until) {}
}
