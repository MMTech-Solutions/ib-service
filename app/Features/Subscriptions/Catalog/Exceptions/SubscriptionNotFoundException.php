<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class SubscriptionNotFoundException extends ApiException
{
    public static function forId(string $subscriptionId): self
    {
        return new self(
            'SUBSCRIPTION_NOT_FOUND',
            "Subscription [{$subscriptionId}] was not found.",
            404,
        );
    }

    public static function openForUser(): self
    {
        return new self(
            'SUBSCRIPTION_NOT_FOUND',
            'No open subscription was found for the authenticated user.',
            404,
        );
    }
}
