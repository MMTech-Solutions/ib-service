<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class UnsupportedRuleStrategyException extends ApiException
{
    public static function forType(string $strategyType): self
    {
        return new self(
            'RULE_STRATEGY_UNSUPPORTED',
            "Strategy [{$strategyType}] is not registered.",
            422,
        );
    }
}
