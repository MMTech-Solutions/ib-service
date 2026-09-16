<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicateSubscriptionReplacementException extends ApiException
{
    public static function forSubscription(string $subscriptionId): self
    {
        return new self(
            'SUBSCRIPTION_REPLACEMENT_CONFLICT',
            "Subscription [{$subscriptionId}] has already been replaced.",
            409,
        );
    }
}
