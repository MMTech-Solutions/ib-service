<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleAssignmentNotFoundException extends ApiException
{
    public static function forId(string $assignmentId): self
    {
        return new self(
            'RULE_ASSIGNMENT_NOT_FOUND',
            "Rule assignment [{$assignmentId}] was not found.",
            404,
        );
    }
}
