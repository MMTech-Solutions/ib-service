<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicateOpenSubscriptionException extends ApiException
{
    public static function forUser(string $externalUserId): self
    {
        return new self(
            'SUBSCRIPTION_OPEN_CONFLICT',
            "User [{$externalUserId}] already has an open subscription.",
            409,
        );
    }
}
