<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Commands;

use App\Features\Rules\Assignments\Http\V1\Requests\ReplaceRuleAssignmentRequest;
use Spatie\LaravelData\Data;

final class ReplaceRuleAssignmentCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly string $assignmentId,
        public readonly string $ruleVersionId,
        public readonly int $lockVersion,
    ) {}

    public static function fromRequest(ReplaceRuleAssignmentRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            assignmentId: (string) $validated['assignment'],
            ruleVersionId: (string) $validated['rule_version_id'],
            lockVersion: (int) $validated['lock_version'],
        );
    }
}
