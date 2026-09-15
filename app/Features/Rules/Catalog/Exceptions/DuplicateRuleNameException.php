<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use RuntimeException;

final class DuplicateRuleNameException extends RuntimeException
{
    public static function forName(string $planId, string $name): self
    {
        return new self("Rule name [{$name}] already exists for plan [{$planId}].");
    }
}
