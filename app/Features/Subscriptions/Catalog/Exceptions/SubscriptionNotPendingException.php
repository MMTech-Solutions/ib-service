<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class SubscriptionNotPendingException extends ApiException
{
    public static function forId(string $subscriptionId): self
    {
        return new self(
            'SUBSCRIPTION_NOT_PENDING',
            "Subscription [{$subscriptionId}] is not pending.",
            409,
        );
    }
}
