<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidRuleConfigurationException extends ApiException
{
    public static function forStrategy(string $strategyType, string $reason): self
    {
        return new self(
            'RULE_CONFIGURATION_INVALID',
            "Configuration for strategy [{$strategyType}] is invalid: {$reason}",
            422,
        );
    }
}
