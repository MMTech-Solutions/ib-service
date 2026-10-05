<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use Spatie\LaravelData\Data;

final class ProgressionRecoveryAttemptData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $attempted_at,
        public readonly ?ProgressionLadderData $ladder,
        public readonly ?string $total_points,
        public readonly ?string $target_program_id,
        public readonly string $stage,
        public readonly string $outcome,
        public readonly ?string $failure_code,
    ) {}
}
