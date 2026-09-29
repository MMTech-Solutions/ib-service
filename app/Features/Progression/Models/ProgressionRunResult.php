<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

use App\Features\Progression\Enums\ProgressionRunResultStatus;
use App\Features\Progression\ValueObjects\ExactDecimal;

final class ProgressionRunResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $runId,
        public readonly string $subscriptionId,
        public readonly ProgressionRunResultStatus $status,
        public readonly ?ExactDecimal $totalPoints,
        public readonly ?string $targetProgramId,
        public readonly int $attemptCount,
    ) {}

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }
}
