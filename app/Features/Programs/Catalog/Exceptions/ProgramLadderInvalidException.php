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
            'The first program entry threshold must be zero; subsequent thresholds must be non-negative and strictly increasing with position.',
            422,
        );
    }
}
