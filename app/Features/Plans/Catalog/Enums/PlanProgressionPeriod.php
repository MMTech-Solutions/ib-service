<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Enums;

enum PlanProgressionPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
