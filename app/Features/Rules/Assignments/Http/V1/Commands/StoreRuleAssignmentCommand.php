<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Commands;

use App\Features\Rules\Assignments\Http\V1\Requests\StoreRuleAssignmentRequest;
use Spatie\LaravelData\Data;

final class StoreRuleAssignmentCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly string $programId,
        public readonly string $moduleId,
        public readonly string $ruleVersionId,
    ) {}

    public static function fromRequest(StoreRuleAssignmentRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            programId: (string) $validated['program_id'],
            moduleId: (string) $validated['module_id'],
            ruleVersionId: (string) $validated['rule_version_id'],
        );
    }
}
