<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Contracts\Repositories;

use App\Features\Rules\Assignments\DTOs\RuleAssignmentListQueryData;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentsPageData;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use Closure;

interface RuleAssignmentRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?RuleAssignment;

    public function findByRuleAndId(string $ruleId, string $assignmentId): ?RuleAssignment;

    public function findActive(string $ruleId, string $programId, string $moduleId): ?RuleAssignment;

    /**
     * Assignments effective at {@see $occurredAt} for the program and module.
     * Interval semantics follow BR-RULE-012: `[starts_at, ends_at)`.
     *
     * @return list<RuleAssignment>
     */
    public function listEffectiveAt(string $programId, string $moduleId, string $occurredAt): array;

    /**
     * Currently active assignments (`ends_at` null) for the program and module.
     *
     * @return list<RuleAssignment>
     */
    public function listActiveForProgramModule(string $programId, string $moduleId): array;

    public function paginateByRule(RuleAssignmentListQueryData $query): RuleAssignmentsPageData;

    public function create(RuleAssignment $assignment): void;

    public function update(RuleAssignment $assignment, int $expectedLockVersion): void;
}
