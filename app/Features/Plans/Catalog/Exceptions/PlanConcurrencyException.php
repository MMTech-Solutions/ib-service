<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanConcurrencyException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PLAN_CONCURRENCY_CONFLICT',
            "Plan [{$planId}] was modified by another operation.",
            409,
        );
    }
}
