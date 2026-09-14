<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Enums;

enum PlanOperationalAction: string
{
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case Archive = 'archive';
}
