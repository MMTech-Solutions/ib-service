<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class SubscriptionConcurrencyException extends ApiException
{
    public static function forSubscription(string $subscriptionId): self
    {
        return new self(
            'SUBSCRIPTION_CONCURRENCY_CONFLICT',
            "Subscription [{$subscriptionId}] was modified by another operation.",
            409,
        );
    }
}
