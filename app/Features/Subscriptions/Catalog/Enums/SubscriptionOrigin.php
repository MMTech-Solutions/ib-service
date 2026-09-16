<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum SubscriptionOrigin: string
{
    case UserApplication = 'user_application';
    case AdminPlanChange = 'admin_plan_change';
}
