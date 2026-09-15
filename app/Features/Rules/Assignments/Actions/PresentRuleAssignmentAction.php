<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Actions;

use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentNotFoundException;
use App\Features\Rules\Assignments\Models\RuleAssignment;

final class PresentRuleAssignmentAction
{
    public function toData(RuleAssignment $assignment): RuleAssignmentData
    {
        return $assignment->toData();
    }

    public function require(?RuleAssignment $assignment, string $assignmentId): RuleAssignment
    {
        if ($assignment === null) {
            throw RuleAssignmentNotFoundException::forId($assignmentId);
        }

        return $assignment;
    }
}
