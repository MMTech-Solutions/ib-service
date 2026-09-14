<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgramReorderConflictException extends ApiException
{
    public static function forPlan(string $planId): self
    {
        return new self(
            'PROGRAM_REORDER_CONFLICT',
            "Programs for plan [{$planId}] changed concurrently and could not be reordered.",
            409,
        );
    }
}
