<?php

declare(strict_types=1);

namespace App\Features\Progression\Models;

use App\Features\Progression\Enums\ContributionScopeType;
use App\Features\Progression\Enums\ContributionStrategyType;
use App\Features\Progression\Exceptions\InvalidActivityEvaluationException;
use App\Features\Progression\ValueObjects\ExactDecimal;
use Carbon\CarbonImmutable;

final class Contribution
{
    private function __construct(
        public readonly string $id,
        public readonly string $evaluationId,
        public readonly string $ruleId,
        public readonly string $ruleVersionId,
        public readonly string $ruleAssignmentId,
        public readonly ?string $programSymbolConfigurationId,
        public readonly ?string $planProgressionTemplateVersionBindingId,
        public readonly ?string $progressionTemplateVersionId,
        public readonly ContributionStrategyType $strategyType,
        public readonly ContributionScopeType $scopeType,
        public readonly ExactDecimal $weight,
        public readonly ExactDecimal $distributionWeight,
        public readonly ExactDecimal $points,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $updatedAt,
    ) {}

    public static function create(
        string $id,
        string $evaluationId,
        string $ruleId,
        string $ruleVersionId,
        string $ruleAssignmentId,
        ExactDecimal $quantity,
        ExactDecimal $weight,
        CarbonImmutable $now,
        ?ExactDecimal $distributionWeight = null,
        ?string $programSymbolConfigurationId = null,
        ?string $planProgressionTemplateVersionBindingId = null,
        ?string $progressionTemplateVersionId = null,
    ): self {
        $distributionWeight ??= ExactDecimal::fromString('1');
        $points = $quantity->multiply($weight)->multiply($distributionWeight);
        $timestamp = $now->utc();

        return new self(
            id: $id,
            evaluationId: $evaluationId,
            ruleId: $ruleId,
            ruleVersionId: $ruleVersionId,
            ruleAssignmentId: $ruleAssignmentId,
            programSymbolConfigurationId: $programSymbolConfigurationId,
            planProgressionTemplateVersionBindingId: $planProgressionTemplateVersionBindingId,
            progressionTemplateVersionId: $progressionTemplateVersionId,
            strategyType: ContributionStrategyType::PointsPerQuantityUnit,
            scopeType: ContributionScopeType::All,
            weight: $weight,
            distributionWeight: $distributionWeight,
            points: $points,
            createdAt: $timestamp,
            updatedAt: $timestamp,
        );
    }

    public static function reconstitute(
        string $id,
        string $evaluationId,
        string $ruleId,
        string $ruleVersionId,
        string $ruleAssignmentId,
        ContributionStrategyType $strategyType,
        ContributionScopeType $scopeType,
        ExactDecimal $weight,
        ExactDecimal $distributionWeight,
        ExactDecimal $points,
        CarbonImmutable $createdAt,
        CarbonImmutable $updatedAt,
        ?string $programSymbolConfigurationId = null,
        ?string $planProgressionTemplateVersionBindingId = null,
        ?string $progressionTemplateVersionId = null,
    ): self {
        if ($strategyType !== ContributionStrategyType::PointsPerQuantityUnit) {
            throw InvalidActivityEvaluationException::forReason(
                'PG1 contributions must use strategy_type points_per_quantity_unit.',
            );
        }

        if ($scopeType !== ContributionScopeType::All) {
            throw InvalidActivityEvaluationException::forReason(
                'PG1 contributions must use scope_type all.',
            );
        }

        return new self(
            id: $id,
            evaluationId: $evaluationId,
            ruleId: $ruleId,
            ruleVersionId: $ruleVersionId,
            ruleAssignmentId: $ruleAssignmentId,
            programSymbolConfigurationId: $programSymbolConfigurationId,
            planProgressionTemplateVersionBindingId: $planProgressionTemplateVersionBindingId,
            progressionTemplateVersionId: $progressionTemplateVersionId,
            strategyType: $strategyType,
            scopeType: $scopeType,
            weight: $weight,
            distributionWeight: $distributionWeight,
            points: $points,
            createdAt: $createdAt->utc(),
            updatedAt: $updatedAt->utc(),
        );
    }
}
