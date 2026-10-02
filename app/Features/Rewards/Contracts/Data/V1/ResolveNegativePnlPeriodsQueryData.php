<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveNegativePnlPeriodsQueryData extends Data
{
    /** @param list<NegativePnlBaselineData> $baselines */
    public function __construct(
        public readonly string $external_user_id,
        public readonly array $baselines = [],
    ) {}
}
