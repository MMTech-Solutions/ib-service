<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgramNotAvailableForPlanException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PROGRAM_NOT_AVAILABLE',
            "Plan [{$planId}] has no programs available.",
            422,
        );
    }
}
