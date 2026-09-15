<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidRuleSlugException extends ApiException
{
    public static function forName(string $name): self
    {
        return new self(
            'RULE_SLUG_INVALID',
            "Rule name [{$name}] does not produce a unique slug.",
            422,
        );
    }
}
