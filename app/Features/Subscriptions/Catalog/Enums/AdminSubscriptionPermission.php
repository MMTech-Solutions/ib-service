<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum AdminSubscriptionPermission: string
{
    case Manage = 'ib.subscriptions.manage';
}
