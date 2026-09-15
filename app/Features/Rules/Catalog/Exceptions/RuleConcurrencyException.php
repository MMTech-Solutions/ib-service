<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleConcurrencyException extends ApiException
{
    public static function forRule(string $ruleId): self
    {
        return new self(
            'RULE_CONCURRENCY_CONFLICT',
            "Rule [{$ruleId}] was modified by another operation.",
            409,
        );
    }
}
