<?php

declare(strict_types=1);

namespace App\Features\Progression\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidExactDecimalException extends ApiException
{
    public static function forValue(string $value, string $detail): self
    {
        return new self(
            'PROGRESSION_INVALID_DECIMAL',
            "Invalid exact decimal [{$value}]: {$detail}",
            422,
        );
    }
}
