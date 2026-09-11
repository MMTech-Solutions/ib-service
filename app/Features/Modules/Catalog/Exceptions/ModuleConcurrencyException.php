<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ModuleConcurrencyException extends ApiException
{
    public static function forModule(string $moduleId): self
    {
        return new self(
            'MODULE_CONCURRENCY_CONFLICT',
            "Module [{$moduleId}] was modified by another operation.",
            409,
        );
    }
}
