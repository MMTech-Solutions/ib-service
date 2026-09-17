<?php

declare(strict_types=1);

namespace App\Features\Progression\Enums;

enum EvaluationStatus: string
{
    case Accepted = 'accepted';
    case Excluded = 'excluded';
}
