<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class SubscriptionNotActiveException extends ApiException
{
    public static function forId(string $subscriptionId): self
    {
        return new self(
            'SUBSCRIPTION_NOT_ACTIVE',
            "Subscription [{$subscriptionId}] is not active.",
            409,
        );
    }
}
