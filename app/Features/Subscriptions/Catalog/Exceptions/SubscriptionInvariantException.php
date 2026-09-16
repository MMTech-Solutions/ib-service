<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class SubscriptionInvariantException extends ApiException
{
    public static function withMessage(string $message): self
    {
        return new self(
            'SUBSCRIPTION_INVARIANT_VIOLATION',
            $message,
            422,
        );
    }
}
