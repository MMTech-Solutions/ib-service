<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Actions;

use App\Features\Rules\Assignments\Enums\RuleAssignmentScopeType;
use App\Features\Rules\Assignments\Factories\RuleAssignmentRepositoryFactory;
use App\Features\Rules\Catalog\Enums\RuleStrategyType;
use App\Features\Rules\Catalog\Factories\RuleRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\PointsContributionContextData;
use App\Features\Rules\Services\Strategies\PointsPerQuantityUnitStrategy;

final class ResolvePointsContributionMatchesAction
{
    public function __construct(
        private readonly RuleAssignmentRepositoryFactory $assignmentRepositoryFactory,
        private readonly RuleRepositoryFactory $ruleRepositoryFactory,
    ) {}

    /**
     * @return list<PointsContributionContextData>
     */
    public function matchesAt(
        string $programId,
        string $moduleId,
        string $unitCode,
        string $occurredAt,
    ): array {
        $assignments = $this->assignmentRepositoryFactory
            ->make()
            ->listEffectiveAt($programId, $moduleId, $occurredAt);

        $matches = [];

        foreach ($assignments as $assignment) {
            $rule = $this->ruleRepositoryFactory->make()->findById($assignment->ruleId);
            if ($rule === null || $rule->strategyType !== RuleStrategyType::PointsPerQuantityUnit->value) {
                continue;
            }

            $version = $rule->findVersion($assignment->ruleVersionId);
            if ($version === null || ! $version->isPublished()) {
                continue;
            }

            $unit = $version->configuration['unit'] ?? null;
            if (! is_string($unit) || $unit !== $unitCode) {
                continue;
            }

            $matches[] = new PointsContributionContextData(
                rule_id: $rule->id,
                rule_version_id: $version->id,
                rule_assignment_id: $assignment->id,
                strategy_type: PointsPerQuantityUnitStrategy::TYPE,
                scope_type: RuleAssignmentScopeType::All->value,
                unit: $unit,
                weight: (string) $version->configuration['points_per_unit'],
            );
        }

        return $matches;
    }
}
