<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Models;

use App\Features\Rules\Assignments\DTOs\RuleAssignmentData;
use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Exceptions\RuleAssignmentInactiveException;

final class RuleAssignment
{
    public function __construct(
        public readonly string $id,
        public readonly string $ruleId,
        public readonly string $ruleVersionId,
        public readonly string $programId,
        public readonly string $moduleId,
        public readonly RuleAssignmentScopeType $scopeType,
        public readonly string $startsAt,
        public ?string $endsAt,
        public int $lockVersion,
        public readonly string $createdAt,
        public string $updatedAt,
    ) {}

    public static function activate(
        string $id,
        string $ruleId,
        string $ruleVersionId,
        string $programId,
        string $moduleId,
        string $now,
    ): self {
        return new self(
            id: $id,
            ruleId: $ruleId,
            ruleVersionId: $ruleVersionId,
            programId: $programId,
            moduleId: $moduleId,
            scopeType: RuleAssignmentScopeType::All,
            startsAt: $now,
            endsAt: null,
            lockVersion: 1,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function isActive(): bool
    {
        return $this->endsAt === null;
    }

    public function withdraw(string $now): void
    {
        if (! $this->isActive()) {
            throw RuleAssignmentInactiveException::forId($this->id);
        }

        $this->endsAt = $now;
        $this->updatedAt = $now;
    }

    public function toData(): RuleAssignmentData
    {
        return new RuleAssignmentData(
            id: $this->id,
            rule_id: $this->ruleId,
            rule_version_id: $this->ruleVersionId,
            program_id: $this->programId,
            module_id: $this->moduleId,
            scope_type: $this->scopeType->value,
            starts_at: $this->startsAt,
            ends_at: $this->endsAt,
            active: $this->isActive(),
            lock_version: $this->lockVersion,
            created_at: $this->createdAt,
            updated_at: $this->updatedAt,
        );
    }
}
