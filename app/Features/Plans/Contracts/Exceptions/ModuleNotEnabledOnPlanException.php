<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class ModuleNotEnabledOnPlanException extends ApiException
{
    /**
     * @param  list<string>  $moduleIds
     */
    public static function forIds(array $moduleIds): self
    {
        $ids = implode(', ', $moduleIds);

        return new self(
            'MODULE_NOT_ENABLED_ON_PLAN',
            "Module(s) [{$ids}] are not enabled on the plan.",
            422,
        );
    }
}
