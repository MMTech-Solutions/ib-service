<?php

declare(strict_types=1);

namespace App\Features\Progression\Actions;

use App\Features\Progression\DTOs\ActivityEvaluationData;
use App\Features\Progression\DTOs\ContributionData;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\Support\ExclusionExplanation;
use Carbon\CarbonImmutable;

final class PresentActivityEvaluationAction
{
    public function toData(ActivityEvaluation $evaluation): ActivityEvaluationData
    {
        return new ActivityEvaluationData(
            id: $evaluation->id,
            module_id: $evaluation->moduleId,
            source_activity_id: $evaluation->sourceActivityId,
            beneficiary_external_user_id: $evaluation->beneficiaryExternalUserId,
            subscription_id: $evaluation->subscriptionId,
            plan_id: $evaluation->planId,
            program_id: $evaluation->programId,
            occurred_at: $this->timestamp($evaluation->occurredAt),
            window_starts_at: $evaluation->window === null
                ? null
                : $this->timestamp($evaluation->window->startsAt),
            window_ends_at: $evaluation->window === null
                ? null
                : $this->timestamp($evaluation->window->endsAt),
            metric_code: $evaluation->metricCode,
            unit_code: $evaluation->unitCode,
            instrument_reference: $evaluation->instrumentReference,
            quantity: $evaluation->quantity->value(),
            status: $evaluation->status->value,
            exclusion_reason: $evaluation->exclusionReason?->value,
            exclusion_explanation: $evaluation->exclusionReason === null
                ? null
                : ExclusionExplanation::for($evaluation->exclusionReason),
            evaluated_at: $this->timestamp($evaluation->evaluatedAt),
            created_at: $this->timestamp($evaluation->createdAt),
            updated_at: $this->timestamp($evaluation->updatedAt),
            contribution: $evaluation->contribution === null
                ? null
                : $this->contributionData($evaluation->contribution),
        );
    }

    private function contributionData(Contribution $contribution): ContributionData
    {
        return new ContributionData(
            id: $contribution->id,
            evaluation_id: $contribution->evaluationId,
            rule_id: $contribution->ruleId,
            rule_version_id: $contribution->ruleVersionId,
            rule_assignment_id: $contribution->ruleAssignmentId,
            strategy_type: $contribution->strategyType->value,
            scope_type: $contribution->scopeType->value,
            weight: $contribution->weight->value(),
            points: $contribution->points->value(),
            created_at: $this->timestamp($contribution->createdAt),
            updated_at: $this->timestamp($contribution->updatedAt),
        );
    }

    private function timestamp(CarbonImmutable $value): string
    {
        return $value->utc()->toIso8601String();
    }
}
