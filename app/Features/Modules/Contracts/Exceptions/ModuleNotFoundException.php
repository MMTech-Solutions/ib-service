<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class ModuleNotFoundException extends ApiException
{
    /**
     * @param  list<string>  $moduleIds
     */
    public static function forIds(array $moduleIds): self
    {
        $ids = implode(', ', $moduleIds);

        return new self(
            'MODULE_NOT_FOUND',
            "Module(s) [{$ids}] were not found.",
            404,
        );
    }
}
