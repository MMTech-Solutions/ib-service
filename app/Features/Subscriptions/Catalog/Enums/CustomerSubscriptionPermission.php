<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum CustomerSubscriptionPermission: string
{
    case Apply = 'ib.subscriptions.apply';
    case Read = 'ib.subscriptions.read';
}
