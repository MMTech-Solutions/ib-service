<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Repositories\PostgreSql;

use App\Features\Rules\Assignments\Contracts\Repositories\RuleAssignmentRepositoryInterface;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentListQueryData;
use App\Features\Rules\Assignments\DTOs\RuleAssignmentsPageData;
use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Exceptions\DuplicateActiveRuleAssignmentException;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentConcurrencyException;
use App\Features\Rules\Assignments\Models\RuleAssignment;
use App\Features\Rules\Assignments\Repositories\PostgreSql\Models\RuleAssignmentRecord;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class PostgreSqlRuleAssignmentRepository implements RuleAssignmentRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    public function findById(string $id): ?RuleAssignment
    {
        $record = RuleAssignmentRecord::query()->whereKey($id)->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findByRuleAndId(string $ruleId, string $assignmentId): ?RuleAssignment
    {
        $record = RuleAssignmentRecord::query()
            ->whereKey($assignmentId)
            ->where('rule_id', $ruleId)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findActive(string $ruleId, string $programId, string $moduleId): ?RuleAssignment
    {
        $record = RuleAssignmentRecord::query()
            ->where('rule_id', $ruleId)
            ->where('program_id', $programId)
            ->where('module_id', $moduleId)
            ->whereNull('ends_at')
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function paginateByRule(RuleAssignmentListQueryData $query): RuleAssignmentsPageData
    {
        $builder = RuleAssignmentRecord::query()
            ->where('rule_id', $query->ruleId)
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        if ($query->programId !== null) {
            $builder->where('program_id', $query->programId);
        }

        if ($query->moduleId !== null) {
            $builder->where('module_id', $query->moduleId);
        }

        if ($query->active === true) {
            $builder->whereNull('ends_at');
        }

        if ($query->active === false) {
            $builder->whereNotNull('ends_at');
        }

        $paginator = $builder->paginate(
            perPage: max(1, $query->perPage),
            page: max(1, $query->page),
        );

        return new RuleAssignmentsPageData(
            assignments: $paginator->getCollection()
                ->map(fn (RuleAssignmentRecord $record) => $this->hydrate($record)->toData())
                ->all(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            lastPage: $paginator->lastPage(),
        );
    }

    public function create(RuleAssignment $assignment): void
    {
        try {
            RuleAssignmentRecord::query()->create($this->attributes($assignment));
        } catch (UniqueConstraintViolationException) {
            throw DuplicateActiveRuleAssignmentException::forContext(
                $assignment->ruleId,
                $assignment->programId,
                $assignment->moduleId,
            );
        }
    }

    public function update(RuleAssignment $assignment, int $expectedLockVersion): void
    {
        $nextLockVersion = $expectedLockVersion + 1;

        $affected = RuleAssignmentRecord::query()
            ->whereKey($assignment->id)
            ->where('lock_version', $expectedLockVersion)
            ->update([
                'ends_at' => $assignment->endsAt,
                'lock_version' => $nextLockVersion,
                'updated_at' => $assignment->updatedAt,
            ]);

        if ($affected !== 1) {
            throw RuleAssignmentConcurrencyException::forAssignment($assignment->id);
        }

        $assignment->lockVersion = $nextLockVersion;
    }

    private function hydrate(RuleAssignmentRecord $record): RuleAssignment
    {
        return new RuleAssignment(
            id: (string) $record->id,
            ruleId: (string) $record->rule_id,
            ruleVersionId: (string) $record->rule_version_id,
            programId: (string) $record->program_id,
            moduleId: (string) $record->module_id,
            scopeType: RuleAssignmentScopeType::from((string) $record->scope_type),
            startsAt: $record->starts_at->utc()->toISOString(),
            endsAt: $record->ends_at?->utc()->toISOString(),
            lockVersion: (int) $record->lock_version,
            createdAt: $record->created_at->utc()->toISOString(),
            updatedAt: $record->updated_at->utc()->toISOString(),
        );
    }

    /** @return array<string, mixed> */
    private function attributes(RuleAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'rule_id' => $assignment->ruleId,
            'rule_version_id' => $assignment->ruleVersionId,
            'program_id' => $assignment->programId,
            'module_id' => $assignment->moduleId,
            'scope_type' => $assignment->scopeType->value,
            'starts_at' => $assignment->startsAt,
            'ends_at' => $assignment->endsAt,
            'lock_version' => $assignment->lockVersion,
            'created_at' => $assignment->createdAt,
            'updated_at' => $assignment->updatedAt,
        ];
    }
}
