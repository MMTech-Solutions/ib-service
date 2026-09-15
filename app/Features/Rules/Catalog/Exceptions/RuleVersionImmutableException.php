<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleVersionImmutableException extends ApiException
{
    public static function forId(string $versionId): self
    {
        return new self(
            'RULE_VERSION_IMMUTABLE',
            "Published rule version [{$versionId}] cannot be modified.",
            422,
        );
    }
}
