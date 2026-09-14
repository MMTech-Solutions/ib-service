<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgramConcurrencyException extends ApiException
{
    public static function forProgram(string $programId): self
    {
        return new self(
            'PROGRAM_CONCURRENCY_CONFLICT',
            "Program [{$programId}] was modified by another operation.",
            409,
        );
    }
}
