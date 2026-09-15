<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use RuntimeException;

final class DuplicateRuleSlugException extends RuntimeException
{
    public static function forSlug(string $planId, string $slug): self
    {
        return new self("Rule slug [{$slug}] already exists for plan [{$planId}].");
    }
}
