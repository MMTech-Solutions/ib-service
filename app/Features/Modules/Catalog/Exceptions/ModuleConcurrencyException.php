<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Exceptions;

use RuntimeException;

final class ModuleConcurrencyException extends RuntimeException
{
    public static function forModule(string $moduleId): self
    {
        return new self("Module [{$moduleId}] was modified by another operation.");
    }
}
