<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Exceptions;

use App\Support\Exceptions\ApiException;

final class InvalidNegativePnlRuleContextException extends ApiException
{
    public static function forContext(): self
    {
        return new self('INVALID_NEGATIVE_PNL_RULE_CONTEXT', 'A published negative PnL version belonging to the plan and exactly one effective assignment are required.', 422);
    }
}
