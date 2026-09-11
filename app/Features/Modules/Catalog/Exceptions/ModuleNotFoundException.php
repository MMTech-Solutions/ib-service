<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ModuleNotFoundException extends ApiException
{
    public static function forId(string $moduleId): self
    {
        return new self(
            'MODULE_NOT_FOUND',
            "Module [{$moduleId}] was not found.",
            404,
        );
    }
}
