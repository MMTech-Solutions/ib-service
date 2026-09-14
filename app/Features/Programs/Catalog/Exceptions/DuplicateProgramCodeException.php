<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use RuntimeException;

final class DuplicateProgramCodeException extends RuntimeException
{
    public static function forCode(string $planId, string $code): self
    {
        return new self("Program code [{$code}] already exists for plan [{$planId}].");
    }
}
