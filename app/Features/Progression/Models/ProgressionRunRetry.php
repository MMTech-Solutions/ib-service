<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

final class ProgressionRunRetry
{
    public function __construct(
        public readonly ProgressionRun $run,
        public readonly ProgressionRunResult $result,
    ) {}
}
