<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class UnsupportedProgressionActivityCapabilityException extends ApiException
{
    public static function forModule(string $moduleId): self
    {
        return new self(
            'UNSUPPORTED_PROGRESSION_ACTIVITY_CAPABILITY',
            "Module [{$moduleId}] has no capability adapter registered for Progression activity queries.",
            422,
        );
    }
}
