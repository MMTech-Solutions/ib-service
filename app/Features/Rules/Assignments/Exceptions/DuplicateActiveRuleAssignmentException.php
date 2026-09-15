<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Exceptions;

use App\Support\Exceptions\ApiException;

final class DuplicateActiveRuleAssignmentException extends ApiException
{
    public static function forContext(string $ruleId, string $programId, string $moduleId): self
    {
        return new self(
            'RULE_ASSIGNMENT_ACTIVE_CONFLICT',
            "An active assignment already exists for rule [{$ruleId}], program [{$programId}] and module [{$moduleId}].",
            409,
        );
    }
}
