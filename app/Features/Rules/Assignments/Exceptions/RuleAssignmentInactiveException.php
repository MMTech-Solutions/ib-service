<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleAssignmentInactiveException extends ApiException
{
    public static function forId(string $assignmentId): self
    {
        return new self(
            'RULE_ASSIGNMENT_INACTIVE',
            "Rule assignment [{$assignmentId}] is no longer active.",
            422,
        );
    }
}
