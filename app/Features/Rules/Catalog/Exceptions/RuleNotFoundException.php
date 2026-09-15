<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleNotFoundException extends ApiException
{
    public static function forId(string $ruleId): self
    {
        return new self(
            'RULE_NOT_FOUND',
            "Rule [{$ruleId}] was not found.",
            404,
        );
    }
}
