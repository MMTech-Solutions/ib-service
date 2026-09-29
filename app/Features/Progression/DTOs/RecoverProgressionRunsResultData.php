<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class RecoverProgressionRunsResultData extends Data
{
    public function __construct(
        public readonly int $results_recovered,
        public readonly int $results_failed,
        public readonly int $runs_completed,
        public readonly int $placements_applied,
        public readonly int $placements_unchanged,
        public readonly int $placements_fixed,
        public readonly int $placements_not_active,
        public readonly int $placements_failed,
    ) {}
}
