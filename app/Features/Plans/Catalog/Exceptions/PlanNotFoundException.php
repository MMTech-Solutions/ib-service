<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanNotFoundException extends ApiException
{
    public static function forId(string $planId): self
    {
        return new self(
            'PLAN_NOT_FOUND',
            "Plan [{$planId}] was not found.",
            404,
        );
    }
}
