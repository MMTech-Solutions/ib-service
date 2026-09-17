<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolvePointsContributionContextQueryData extends Data
{
    public function __construct(
        public readonly string $program_id,
        public readonly string $module_id,
        public readonly string $metric_code,
        public readonly string $unit_code,
        public readonly string $occurred_at,
    ) {}
}
