<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleVersionNotAssignableException extends ApiException
{
    public static function forId(string $versionId): self
    {
        return new self(
            'RULE_VERSION_NOT_ASSIGNABLE',
            "Rule version [{$versionId}] is not a published version of the rule.",
            422,
        );
    }
}
