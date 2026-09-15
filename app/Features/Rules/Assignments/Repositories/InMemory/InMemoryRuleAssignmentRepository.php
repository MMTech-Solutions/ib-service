<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Repositories\InMemory;

use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentListQueryData;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentsPageData;
use App\Features\Rules\Assignments\Exceptions\DuplicateActiveRuleAssignmentException;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentConcurrencyException;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use Closure;
use Throwable;

final class InMemoryRuleAssignmentRepository implements RuleAssignmentRepositoryInterface
{
    /** @var array<string, RuleAssignment> */
    private array $assignments = [];

    public function transaction(Closure $callback): mixed
    {
        $snapshot = unserialize(serialize($this->assignments), ['allowed_classes' => true]);

        try {
            return $callback();
        } catch (Throwable $throwable) {
            $this->assignments = $snapshot;
            throw $throwable;
        }
    }

    public function findById(string $id): ?RuleAssignment
    {
        $assignment = $this->assignments[$id] ?? null;

        return $assignment === null ? null : $this->copy($assignment);
    }

    public function findByRuleAndId(string $ruleId, string $assignmentId): ?RuleAssignment
    {
        $assignment = $this->findById($assignmentId);
        if ($assignment === null || $assignment->ruleId !== $ruleId) {
            return null;
        }

        return $assignment;
    }

    public function findActive(string $ruleId, string $programId, string $moduleId): ?RuleAssignment
    {
        foreach ($this->assignments as $assignment) {
            if (
                $assignment->ruleId === $ruleId
                && $assignment->programId === $programId
                && $assignment->moduleId === $moduleId
                && $assignment->isActive()
            ) {
                return $this->copy($assignment);
            }
        }

        return null;
    }

    public function paginateByRule(RuleAssignmentListQueryData $query): RuleAssignmentsPageData
    {
        $filtered = array_values(array_filter(
            $this->assignments,
            static function (RuleAssignment $assignment) use ($query): bool {
                if ($assignment->ruleId !== $query->ruleId) {
                    return false;
                }

                if ($query->programId !== null && $assignment->programId !== $query->programId) {
                    return false;
                }

                if ($query->moduleId !== null && $assignment->moduleId !== $query->moduleId) {
                    return false;
                }

                if ($query->active === true && ! $assignment->isActive()) {
                    return false;
                }

                if ($query->active === false && $assignment->isActive()) {
                    return false;
                }

                return true;
            },
        ));

        usort(
            $filtered,
            static fn (RuleAssignment $a, RuleAssignment $b): int => [$b->startsAt, $b->id] <=> [$a->startsAt, $a->id],
        );

        $total = count($filtered);
        $perPage = max(1, $query->perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $query->page), $lastPage);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return new RuleAssignmentsPageData(
            assignments: array_map(
                fn (RuleAssignment $assignment) => $this->copy($assignment)->toData(),
                $slice,
            ),
            currentPage: $page,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
        );
    }

    public function create(RuleAssignment $assignment): void
    {
        $this->assertNoActiveDuplicate($assignment);
        $this->assignments[$assignment->id] = $this->copy($assignment);
    }

    public function update(RuleAssignment $assignment, int $expectedLockVersion): void
    {
        $stored = $this->assignments[$assignment->id] ?? null;
        if ($stored === null || $stored->lockVersion !== $expectedLockVersion) {
            throw RuleAssignmentConcurrencyException::forAssignment($assignment->id);
        }

        $assignment->lockVersion = $expectedLockVersion + 1;
        $this->assignments[$assignment->id] = $this->copy($assignment);
    }

    private function assertNoActiveDuplicate(RuleAssignment $assignment): void
    {
        if (! $assignment->isActive()) {
            return;
        }

        foreach ($this->assignments as $stored) {
            if (
                $stored->id !== $assignment->id
                && $stored->ruleId === $assignment->ruleId
                && $stored->programId === $assignment->programId
                && $stored->moduleId === $assignment->moduleId
                && $stored->isActive()
            ) {
                throw DuplicateActiveRuleAssignmentException::forContext(
                    $assignment->ruleId,
                    $assignment->programId,
                    $assignment->moduleId,
                );
            }
        }
    }

    private function copy(RuleAssignment $assignment): RuleAssignment
    {
        /** @var RuleAssignment $copy */
        $copy = unserialize(serialize($assignment), ['allowed_classes' => true]);

        return $copy;
    }
}
