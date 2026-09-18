<?php

declare(strict_types=1);

namespace App\Features\Progression\Actions;

use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Plans\Contracts\Data\V1\AssertEnabledModuleIdsQueryData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanProgressionContextQueryData;
use App\Features\Plans\Contracts\Exceptions\ModuleNotEnabledOnPlanException;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Exceptions\ModuleNotSelectedOnProgramException;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Progression\Contracts\Data\V1\NormalizedActivityData;
use App\Features\Progression\Enums\ExclusionReason;
use App\Features\Progression\Exceptions\InvalidExactDecimalException;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\Contribution;
use App\Features\Progression\Support\DeriveProgressionWindowFromPeriod;
use App\Features\Progression\ValueObjects\ExactDecimal;
use App\Features\Progression\ValueObjects\ProgressionWindow;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Resuelve el contexto de una actividad normalizada y construye la evaluación
 * accepted/excluded inmutable lista para persistir.
 */
final class BuildActivityEvaluationDecisionAction
{
    public function __construct(
        private readonly ResolveSubscriptionContextPort $subscriptions,
        private readonly ResolvePlanProgressionContextPort $planProgression,
        private readonly ResolvePlanContextPort $planContext,
        private readonly ResolveProgramContextPort $programs,
        private readonly ResolveModulesPort $modules,
        private readonly ResolvePointsContributionContextPort $rules,
        private readonly DeriveProgressionWindowFromPeriod $deriveWindow,
    ) {}

    public function build(
        NormalizedActivityData $activity,
        string $planId,
        bool $resumingAfterPause,
        CarbonImmutable $evaluatedAt,
    ): ActivityEvaluation {
        $occurredAt = CarbonImmutable::parse($activity->occurred_at)->utc();
        $evaluationId = (string) Str::uuid7();

        $quantity = $this->tryQuantity($activity->quantity);
        if ($quantity === null) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::ScaleExceeded,
            );
        }

        $subscriptionResult = $this->subscriptions->resolve(new ResolveSubscriptionContextQueryData(
            external_user_id: $activity->subject_external_user_id,
            occurred_at: $occurredAt->toISOString(),
        ));

        if (! $subscriptionResult->found() || $subscriptionResult->context === null) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::NoActiveSubscription,
                quantity: $quantity,
            );
        }

        $subscription = $subscriptionResult->context;
        if ($subscription->plan_id !== $planId) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::NoActiveSubscription,
                quantity: $quantity,
            );
        }

        if ($subscription->placement_condition === 'fixed') {
            $window = $this->windowForPlanAt($planId, $occurredAt);

            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::PlacementFixed,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $planAtOccurrence = $this->planProgression->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $planId,
            occurred_at: $occurredAt->toISOString(),
        ));

        $window = $this->deriveWindow->derive($planAtOccurrence->progression_period, $occurredAt);

        if (! $planAtOccurrence->is_active) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::PlanInactive,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        if ($resumingAfterPause && ! $evaluatedAt->lt($window->endsAt)) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::WindowClosedAfterPause,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $moduleSummaries = $this->modules->findByIds([$activity->module_id]);
        $module = $moduleSummaries[0] ?? null;
        if ($module === null || ! $module->is_active) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::ModuleInactive,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        try {
            $this->planContext->assertEnabledModuleIds(new AssertEnabledModuleIdsQueryData(
                plan_id: $planId,
                module_ids: [$activity->module_id],
            ));
        } catch (ModuleNotEnabledOnPlanException) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::ModuleNotSelected,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        try {
            $this->programs->assertSelectedModule(new AssertSelectedModuleQueryData(
                plan_id: $planId,
                program_id: $subscription->program_id,
                module_id: $activity->module_id,
            ));
        } catch (ModuleNotSelectedOnProgramException) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::ModuleNotSelected,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $ruleResult = $this->rules->resolve(new ResolvePointsContributionContextQueryData(
            program_id: $subscription->program_id,
            module_id: $activity->module_id,
            metric_code: $activity->metric_code,
            unit_code: $activity->unit_code,
            occurred_at: $occurredAt->toISOString(),
        ));

        if (! $ruleResult->found() || $ruleResult->context === null) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::NoApplicableRule,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $rule = $ruleResult->context;
        if ($rule->unit !== $activity->unit_code) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::UnitMismatch,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $weight = $this->tryQuantity($rule->weight);
        if ($weight === null) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::ScaleExceeded,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $planAtCommit = $this->planProgression->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $planId,
            occurred_at: $evaluatedAt->toISOString(),
        ));

        if (! $planAtCommit->is_active) {
            return $this->excluded(
                evaluationId: $evaluationId,
                activity: $activity,
                occurredAt: $occurredAt,
                evaluatedAt: $evaluatedAt,
                reason: ExclusionReason::PlanInactive,
                quantity: $quantity,
                subscriptionId: $subscription->subscription_id,
                planId: $subscription->plan_id,
                programId: $subscription->program_id,
                window: $window,
            );
        }

        $contribution = Contribution::create(
            id: (string) Str::uuid7(),
            evaluationId: $evaluationId,
            ruleId: $rule->rule_id,
            ruleVersionId: $rule->rule_version_id,
            ruleAssignmentId: $rule->rule_assignment_id,
            quantity: $quantity,
            weight: $weight,
            now: $evaluatedAt,
        );

        return ActivityEvaluation::accepted([
            'id' => $evaluationId,
            'moduleId' => $activity->module_id,
            'sourceActivityId' => $activity->source_activity_id,
            'beneficiaryExternalUserId' => $activity->subject_external_user_id,
            'subscriptionId' => $subscription->subscription_id,
            'planId' => $subscription->plan_id,
            'programId' => $subscription->program_id,
            'occurredAt' => $occurredAt,
            'window' => $window,
            'metricCode' => $activity->metric_code,
            'unitCode' => $activity->unit_code,
            'instrumentReference' => $activity->instrument_reference,
            'quantity' => $quantity,
            'evaluatedAt' => $evaluatedAt,
            'contribution' => $contribution,
        ]);
    }

    private function tryQuantity(string $value): ?ExactDecimal
    {
        try {
            return ExactDecimal::fromString($value);
        } catch (InvalidExactDecimalException) {
            return null;
        }
    }

    private function windowForPlanAt(string $planId, CarbonImmutable $occurredAt): ?ProgressionWindow
    {
        $plan = $this->planProgression->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $planId,
            occurred_at: $occurredAt->toISOString(),
        ));

        return $this->deriveWindow->derive($plan->progression_period, $occurredAt);
    }

    private function excluded(
        string $evaluationId,
        NormalizedActivityData $activity,
        CarbonImmutable $occurredAt,
        CarbonImmutable $evaluatedAt,
        ExclusionReason $reason,
        ?ExactDecimal $quantity = null,
        ?string $subscriptionId = null,
        ?string $planId = null,
        ?string $programId = null,
        ?ProgressionWindow $window = null,
    ): ActivityEvaluation {
        return ActivityEvaluation::excluded([
            'id' => $evaluationId,
            'moduleId' => $activity->module_id,
            'sourceActivityId' => $activity->source_activity_id,
            'beneficiaryExternalUserId' => $activity->subject_external_user_id,
            'subscriptionId' => $subscriptionId,
            'planId' => $planId,
            'programId' => $programId,
            'occurredAt' => $occurredAt,
            'window' => $window,
            'metricCode' => $activity->metric_code,
            'unitCode' => $activity->unit_code,
            'instrumentReference' => $activity->instrument_reference,
            'quantity' => $quantity ?? ExactDecimal::fromString('0'),
            'exclusionReason' => $reason,
            'evaluatedAt' => $evaluatedAt,
        ]);
    }
}
