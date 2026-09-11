<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Exceptions;

use RuntimeException;

final class DuplicateModuleCodeException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Module code [{$code}] already exists.");
    }
}
