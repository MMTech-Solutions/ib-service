<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Http\V1\Commands;

use App\Features\Rules\Assignments\DTOs\RuleAssignmentListQueryData;
use App\Features\Rules\Assignments\Http\V1\Requests\ListRuleAssignmentsRequest;
use Spatie\LaravelData\Data;

final class ListRuleAssignmentsCommand extends Data
{
    public function __construct(
        public readonly string $planId,
        public readonly string $ruleId,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $programId,
        public readonly ?string $moduleId,
        public readonly ?bool $active,
    ) {}

    public static function fromRequest(ListRuleAssignmentsRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            planId: (string) $validated['plan'],
            ruleId: (string) $validated['rule'],
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 100),
            programId: isset($validated['program_id']) ? (string) $validated['program_id'] : null,
            moduleId: isset($validated['module_id']) ? (string) $validated['module_id'] : null,
            active: array_key_exists('active', $validated) ? (bool) $validated['active'] : null,
        );
    }

    public function toQueryData(): RuleAssignmentListQueryData
    {
        return new RuleAssignmentListQueryData(
            ruleId: $this->ruleId,
            page: $this->page,
            perPage: $this->perPage,
            programId: $this->programId,
            moduleId: $this->moduleId,
            active: $this->active,
        );
    }
}
