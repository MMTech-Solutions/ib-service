<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Actions;

use App\Features\Rules\Assignments\Exceptions\DuplicatePointsPerQuantityUnitAssignmentException;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;

final class AssertUniquePointsPerQuantityUnitAssignmentAction
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $assignmentRepositoryFactory,
        private readonly RuleRepositoryFactory $ruleRepositoryFactory,
    ) {}

    public function assert(
        string $programId,
        string $moduleId,
        string $unit,
        ?string $excludeRuleId = null,
    ): void {
        $assignments = $this->assignmentRepositoryFactory
            ->make()
            ->listActiveForProgramModule($programId, $moduleId);

        foreach ($assignments as $assignment) {
            if ($excludeRuleId !== null && $assignment->ruleId === $excludeRuleId) {
                continue;
            }

            $rule = $this->ruleRepositoryFactory->make()->findById($assignment->ruleId);
            if ($rule === null || $rule->strategyType !== RuleStrategyType::PointsPerQuantityUnit->value) {
                continue;
            }

            $version = $rule->findVersion($assignment->ruleVersionId);
            if ($version === null) {
                continue;
            }

            $configuredUnit = $version->configuration['unit'] ?? null;
            if (is_string($configuredUnit) && $configuredUnit === $unit) {
                throw DuplicatePointsPerQuantityUnitAssignmentException::forContext(
                    $programId,
                    $moduleId,
                    $unit,
                );
            }
        }
    }
}
