<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class PlanArchivedException extends ApiException
{
    public static function forId(string $planId): self
    {
        return new self(
            'PLAN_ARCHIVED',
            "Plan [{$planId}] is archived and does not allow program mutations.",
            422,
        );
    }
}
