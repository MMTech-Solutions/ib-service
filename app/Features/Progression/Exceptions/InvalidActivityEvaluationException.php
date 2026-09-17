<?php

declare(strict_types=1);

namespace App\Features\Progression\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidActivityEvaluationException extends ApiException
{
    public static function forReason(string $reason): self
    {
        return new self(
            'PROGRESSION_INVALID_EVALUATION',
            $reason,
            422,
        );
    }
}
