<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanProgressionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Progression\Actions\BuildActivityEvaluationDecisionAction;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Data\V1\ResolveReferralUplineQueryData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\Contracts\Ports\Output\ResolveReferralUplinePort;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesData;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesResult;
use App\Features\Progression\DTOs\RetryableProgressionFailureData;
use App\Features\Progression\Factories\ActivityDistributionRepositoryFactory;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use App\Features\Progression\Models\ActivityDistribution;
use App\Features\Progression\Models\ActivityEvaluation;
use App\Features\Progression\Models\DistributionBeneficiary;
use App\Features\Progression\Services\ProgressionExecutionEvidence;
use App\SharedFeatures\Clock\DomainClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recorre páginas M3 y deja una evaluación durable por actividad y beneficiario.
 */
final class EvaluateProgressionActivitiesUseCase
{
    public function __construct(
        private readonly ResolvePlanProgressionContextPort $planProgression,
        private readonly FetchProgressionActivitiesPort $activities,
        private readonly BuildActivityEvaluationDecisionAction $buildDecision,
        private readonly ActivityEvaluationRepositoryFactory $evaluationRepositoryFactory,
        private readonly ActivityDistributionRepositoryFactory $distributionRepositoryFactory,
        private readonly ResolveReferralUplinePort $referralUpline,
        private readonly ProgressionExecutionEvidence $evidence,
    ) {}

    public function execute(EvaluateProgressionActivitiesData $input): EvaluateProgressionActivitiesResult
    {
        $evaluatedAt = app(DomainClock::class)->now();

        $planNow = $this->planProgression->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $input->plan_id,
            occurred_at: $evaluatedAt->toISOString(),
        ));

        if (! $planNow->is_active) {
            return EvaluateProgressionActivitiesResult::skippedPlanInactive();
        }

        $repository = $this->evaluationRepositoryFactory->make();
        $evaluations = [];
        $retryableFailures = [];
        $cursor = null;

        do {
            $page = $this->activities->fetch(new FetchProgressionActivitiesQueryData(
                module_id: $input->module_id,
                occurred_from: $input->occurred_from,
                occurred_until: $input->occurred_until,
                cursor: $cursor,
                limit: $input->limit,
            ));

            if ($page->isRejected() || $page->module_condition === 'inactive') {
                return new EvaluateProgressionActivitiesResult(EvaluateProgressionActivitiesResult::OUTCOME_REJECTED_MODULE_INACTIVE, $evaluations, $retryableFailures);
            }

            if ($page->module_condition === 'paused') {
                return new EvaluateProgressionActivitiesResult(EvaluateProgressionActivitiesResult::OUTCOME_DEFERRED_MODULE_PAUSED, $evaluations, $retryableFailures);
            }

            foreach ($page->activities as $activity) {
                $distributionRepository = $this->distributionRepositoryFactory->make();
                $distribution = $distributionRepository->findBySourceActivity($activity->module_id, $activity->source_activity_id);
                if ($distribution === null) {
                    $resolved = $this->referralUpline->resolve(new ResolveReferralUplineQueryData($activity->subject_external_user_id));
                    if (! $resolved->isResolved() || $resolved->resolved_at === null) {
                        $failure = RetryableProgressionFailureData::activity($activity, $resolved->failure_code ?? 'invalid_response');
                        $retryableFailures[] = $failure;
                        $this->evidence->count('retryable_failures');
                        Log::warning('progression.activity_distribution_retryable', $failure->toArray());

                        continue;
                    }

                    $distribution = $distributionRepository->record(ActivityDistribution::resolve(
                        id: (string) Str::uuid7(),
                        moduleId: $activity->module_id,
                        sourceActivityId: $activity->source_activity_id,
                        sourceExternalUserId: $activity->subject_external_user_id,
                        resolvedAt: CarbonImmutable::parse($resolved->resolved_at)->utc(),
                        beneficiaries: array_map(
                            static fn ($beneficiary): DistributionBeneficiary => new DistributionBeneficiary(
                                $beneficiary->beneficiary_external_user_id,
                                $beneficiary->distribution_level,
                            ),
                            $resolved->beneficiaries,
                        ),
                    ));
                }

                $this->evidence->id('distribution_ids', $distribution->id);
                foreach ($distribution->beneficiaries as $beneficiary) {
                    $existing = $repository->findByIdempotencyKey($activity->module_id, $activity->source_activity_id, $beneficiary->beneficiaryExternalUserId, $beneficiary->distributionLevel);
                    if ($existing !== null) {
                        $evaluations[] = $existing;
                        $this->recordEvidence($existing);

                        continue;
                    }

                    try {
                        $decision = $this->buildDecision->build(
                            activity: $activity,
                            planId: $input->plan_id,
                            resumingAfterPause: $input->resuming_after_pause,
                            evaluatedAt: $evaluatedAt,
                            beneficiaryExternalUserId: $beneficiary->beneficiaryExternalUserId,
                            activityDistributionId: $distribution->id,
                            distributionLevel: $beneficiary->distributionLevel,
                            distributionResolvedAt: $distribution->resolvedAt,
                        );
                        $evaluation = $repository->record($decision);
                        $evaluations[] = $evaluation;
                        $this->recordEvidence($evaluation);
                    } catch (Throwable) {
                        $failure = new RetryableProgressionFailureData($activity->module_id, $activity->source_activity_id, 'beneficiary_evaluation_failed', $beneficiary->beneficiaryExternalUserId, $beneficiary->distributionLevel);
                        $retryableFailures[] = $failure;
                        $this->evidence->count('retryable_failures');
                        Log::warning('progression.beneficiary_evaluation_retryable', $failure->toArray());
                    }
                }
            }

            $cursor = $page->next_cursor;
        } while ($cursor !== null);

        return EvaluateProgressionActivitiesResult::evaluated($evaluations, $retryableFailures);
    }

    private function recordEvidence(ActivityEvaluation $evaluation): void
    {
        $this->evidence->id('evaluation_ids', $evaluation->id);
        $this->evidence->count('evaluations');
        $this->evidence->count($evaluation->status->value === 'accepted' ? 'accepted' : 'excluded');
    }
}
