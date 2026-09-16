<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanNotEligibleForSubscriptionException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PLAN_NOT_ELIGIBLE_FOR_SUBSCRIPTION',
            "Plan [{$planId}] is not active or is archived.",
            422,
        );
    }
}
