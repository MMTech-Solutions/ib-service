<?php

declare(strict_types=1);

namespace App\Features\Progression\Enums;

enum ProgressionRunResultStatus: string
{
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this !== self::Failed;
    }
}
