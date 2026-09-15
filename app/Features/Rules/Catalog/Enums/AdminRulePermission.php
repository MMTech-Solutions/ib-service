<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Enums;

enum AdminRulePermission: string
{
    case Manage = 'ib.rules.manage';
}
