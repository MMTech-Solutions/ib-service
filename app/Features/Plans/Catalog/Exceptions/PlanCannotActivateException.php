<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanCannotActivateException extends ApiException
{
    public static function missingOperationalModule(string $planId): self
    {
        return new self(
            'PLAN_CANNOT_ACTIVATE',
            "Plan [{$planId}] cannot be activated without at least one operational module.",
            422,
        );
    }
}
