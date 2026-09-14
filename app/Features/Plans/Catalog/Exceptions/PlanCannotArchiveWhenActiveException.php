<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanCannotArchiveWhenActiveException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PLAN_CANNOT_ARCHIVE',
            "Active plan [{$planId}] must be deactivated before it can be archived.",
            422,
        );
    }
}
