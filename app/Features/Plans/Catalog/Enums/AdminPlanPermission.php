<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Enums;

enum AdminPlanPermission: string
{
    case Manage = 'ib.plans.manage';
}
