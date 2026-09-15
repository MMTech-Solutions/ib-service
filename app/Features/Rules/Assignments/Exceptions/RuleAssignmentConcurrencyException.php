<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Exceptions;

use App\Support\Exceptions\ApiException;

final class RuleAssignmentConcurrencyException extends ApiException
{
    public static function forAssignment(string $assignmentId): self
    {
        return new self(
            'RULE_ASSIGNMENT_CONCURRENCY_CONFLICT',
            "Rule assignment [{$assignmentId}] was modified by another operation.",
            409,
        );
    }
}
