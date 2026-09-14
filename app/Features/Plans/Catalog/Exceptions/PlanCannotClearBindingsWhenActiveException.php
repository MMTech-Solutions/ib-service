<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanCannotClearBindingsWhenActiveException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PLAN_BINDINGS_REQUIRED',
            "Active plan [{$planId}] must keep at least one module binding.",
            422,
        );
    }
}
