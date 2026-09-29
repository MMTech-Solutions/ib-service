<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Enums;

enum ProgressionPlacementOutcome: string
{
    case Applied = 'applied';
    case Unchanged = 'unchanged';
    case Fixed = 'fixed';
    case NotActive = 'not_active';
}
