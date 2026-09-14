<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicatePlanCodeConflictException extends ApiException
{
    public static function forCode(string $code): self
    {
        return new self(
            'PLAN_CODE_CONFLICT',
            "Plan code [{$code}] already exists.",
            409,
        );
    }
}
