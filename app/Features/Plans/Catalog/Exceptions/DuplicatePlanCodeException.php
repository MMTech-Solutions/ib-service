<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Exceptions;

use RuntimeException;

final class DuplicatePlanCodeException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Plan code [{$code}] already exists.");
    }
}
