<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicateProgramCodeConflictException extends ApiException
{
    public static function forCode(string $code): self
    {
        return new self(
            'PROGRAM_CODE_CONFLICT',
            "Program code [{$code}] already exists for this plan.",
            409,
        );
    }
}
