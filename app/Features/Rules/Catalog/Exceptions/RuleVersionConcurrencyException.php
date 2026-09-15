<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleVersionConcurrencyException extends ApiException
{
    public static function forVersion(string $versionId): self
    {
        return new self(
            'RULE_VERSION_CONCURRENCY_CONFLICT',
            "Rule version [{$versionId}] was modified by another operation.",
            409,
        );
    }
}
