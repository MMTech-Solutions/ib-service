<?php

declare(strict_types=1);

namespace App\Features\Progression\UseCases;

use App\Features\Plans\Contracts\Data\V1\ResolvePlanProgressionContextQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Progression\Actions\BuildActivityEvaluationDecisionAction;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesData;
use App\Features\Progression\DTOs\EvaluateProgressionActivitiesResult;
use App\Features\Progression\Factories\ActivityEvaluationRepositoryFactory;
use Carbon\CarbonImmutable;

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
    ) {}

    public function execute(EvaluateProgressionActivitiesData $input): EvaluateProgressionActivitiesResult
    {
        $evaluatedAt = CarbonImmutable::now('UTC');

        $planNow = $this->planProgression->resolve(new ResolvePlanProgressionContextQueryData(
            plan_id: $input->plan_id,
            occurred_at: $evaluatedAt->toISOString(),
        ));

        if (! $planNow->is_active) {
            return EvaluateProgressionActivitiesResult::skippedPlanInactive();
        }

        $repository = $this->evaluationRepositoryFactory->make();
        $evaluations = [];
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
                return EvaluateProgressionActivitiesResult::rejectedModuleInactive();
            }

            if ($page->module_condition === 'paused') {
                return EvaluateProgressionActivitiesResult::deferredModulePaused();
            }

            foreach ($page->activities as $activity) {
                $existing = $repository->findByIdempotencyKey(
                    $activity->module_id,
                    $activity->source_activity_id,
                    $activity->subject_external_user_id,
                );

                if ($existing !== null) {
                    $evaluations[] = $existing;

                    continue;
                }

                $decision = $this->buildDecision->build(
                    activity: $activity,
                    planId: $input->plan_id,
                    resumingAfterPause: $input->resuming_after_pause,
                    evaluatedAt: $evaluatedAt,
                );

                $evaluations[] = $repository->record($decision);
            }

            $cursor = $page->next_cursor;
        } while ($cursor !== null);

        return EvaluateProgressionActivitiesResult::evaluated($evaluations);
    }
}
