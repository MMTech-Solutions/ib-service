<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Enums;

enum SubscriptionActorKind: string
{
    case Iam = 'iam';
    case System = 'system';
}
