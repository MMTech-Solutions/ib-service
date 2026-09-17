<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class AmbiguousSubscriptionContextException extends ApiException
{
    public static function forUserAt(string $externalUserId, string $occurredAt): self
    {
        return new self(
            'AMBIGUOUS_SUBSCRIPTION_CONTEXT',
            "More than one subscription placement covers external user [{$externalUserId}] at [{$occurredAt}].",
            409,
        );
    }
}
