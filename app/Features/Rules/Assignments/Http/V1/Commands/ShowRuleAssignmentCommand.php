<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Commands;

use App\Features\Rules\Assignments\Http\V1\Requests\ShowRuleAssignmentRequest;
use Spatie\LaravelData\Data;

final class ShowRuleAssignmentCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly string $assignmentId,
    ) {}

    public static function fromRequest(ShowRuleAssignmentRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            assignmentId: (string) $validated['assignment'],
        );
    }
}
