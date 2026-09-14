<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class ModuleInactiveException extends ApiException
{
    /**
     * @param  list<string>  $moduleIds
     */
    public static function forIds(array $moduleIds): self
    {
        $ids = implode(', ', $moduleIds);

        return new self(
            'MODULE_INACTIVE',
            "Module(s) [{$ids}] are not selectable because they are inactive.",
            422,
        );
    }
}
