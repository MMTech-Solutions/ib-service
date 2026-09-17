<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidProgressionActivityQueryException extends ApiException
{
    public static function withMessage(string $message): self
    {
        return new self(
            'INVALID_PROGRESSION_ACTIVITY_QUERY',
            $message,
            422,
        );
    }
}
