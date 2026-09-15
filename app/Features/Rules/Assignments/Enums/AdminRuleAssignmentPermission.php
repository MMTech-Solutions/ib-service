<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Enums;

enum AdminRuleAssignmentPermission: string
{
    case Manage = 'ib.rules.manage';
}
