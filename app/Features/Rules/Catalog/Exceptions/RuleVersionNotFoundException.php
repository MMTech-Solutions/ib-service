<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleVersionNotFoundException extends ApiException
{
    public static function forId(string $versionId): self
    {
        return new self(
            'RULE_VERSION_NOT_FOUND',
            "Rule version [{$versionId}] was not found.",
            404,
        );
    }
}
