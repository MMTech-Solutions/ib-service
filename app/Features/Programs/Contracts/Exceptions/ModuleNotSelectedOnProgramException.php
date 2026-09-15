<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class ModuleNotSelectedOnProgramException extends ApiException
{
    public static function forIds(string $programId, string $moduleId): self
    {
        return new self(
            'MODULE_NOT_SELECTED_ON_PROGRAM',
            "Module [{$moduleId}] is not selected on program [{$programId}].",
            422,
        );
    }
}
