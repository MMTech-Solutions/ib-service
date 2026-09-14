<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgramNotFoundException extends ApiException
{
    public static function forId(string $programId): self
    {
        return new self(
            'PROGRAM_NOT_FOUND',
            "Program [{$programId}] was not found.",
            404,
        );
    }
}
