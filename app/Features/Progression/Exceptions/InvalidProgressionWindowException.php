<?php

declare(strict_types=1);

namespace App\Features\Progression\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidProgressionWindowException extends ApiException
{
    public static function forOrder(string $startsAt, string $endsAt): self
    {
        return new self(
            'PROGRESSION_INVALID_WINDOW',
            "Progression window end [{$endsAt}] must be after start [{$startsAt}].",
            422,
        );
    }
}
