<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class CloseProgressionWindowsResultData extends Data
{
    public function __construct(
        public readonly int $runs_created,
        public readonly int $runs_completed,
        public readonly int $runs_with_errors,
        public readonly int $results_completed,
        public readonly int $results_skipped,
        public readonly int $results_failed,
        public readonly int $placements_applied = 0,
        public readonly int $placements_unchanged = 0,
        public readonly int $placements_fixed = 0,
        public readonly int $placements_not_active = 0,
        public readonly int $placements_failed = 0,
    ) {}
}
