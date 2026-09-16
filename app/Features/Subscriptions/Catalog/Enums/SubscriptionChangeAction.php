<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum SubscriptionChangeAction: string
{
    case Request = 'request';
    case Approve = 'approve';
    case Reject = 'reject';
    case Cancel = 'cancel';
    case ChangePlanOut = 'change_plan_out';
    case ChangePlanIn = 'change_plan_in';
    case ChangeProgram = 'change_program';
    case FixPlacement = 'fix_placement';
    case ReleasePlacement = 'release_placement';
}
