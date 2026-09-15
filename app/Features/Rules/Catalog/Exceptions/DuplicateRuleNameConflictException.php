<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicateRuleNameConflictException extends ApiException
{
    public static function forName(string $name): self
    {
        return new self(
            'RULE_NAME_CONFLICT',
            "Rule name [{$name}] already exists for this plan.",
            409,
        );
    }
}
