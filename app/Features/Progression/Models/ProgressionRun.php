<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

use App\Features\Progression\Enums\ProgressionRunStatus;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use Carbon\CarbonImmutable;

final class ProgressionRun
{
    public function __construct(
        public readonly string $id,
        public readonly string $planId,
        public readonly ProgressionWindow $window,
        public readonly ProgressionRunStatus $status,
        public readonly ?CarbonImmutable $startedAt,
        public readonly ?CarbonImmutable $completedAt,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === ProgressionRunStatus::Completed;
    }
}
