<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicateRuleSlugConflictException extends ApiException
{
    public static function forSlug(string $slug): self
    {
        return new self(
            'RULE_SLUG_CONFLICT',
            "Rule slug [{$slug}] already exists for this plan.",
            409,
        );
    }
}
