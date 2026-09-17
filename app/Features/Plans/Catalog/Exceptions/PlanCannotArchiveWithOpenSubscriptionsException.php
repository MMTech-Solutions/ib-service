<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanCannotArchiveWithOpenSubscriptionsException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PLAN_HAS_OPEN_SUBSCRIPTIONS',
            "Plan [{$planId}] cannot be archived while it has pending or active subscriptions.",
            422,
        );
    }
}
