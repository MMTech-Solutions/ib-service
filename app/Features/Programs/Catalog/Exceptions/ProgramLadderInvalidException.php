<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgramLadderInvalidException extends ApiException
{
    public static function forPlan(): self
    {
        return new self(
            'PROGRAM_LADDER_INVALID',
            'Program entry thresholds must be non-negative integers strictly increasing with position.',
            422,
        );
    }
}
