<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use App\Features\Progression\Models\ActivityEvaluation;

/**
 * Resultado interno de una corrida de evaluación pull-only.
 */
final class EvaluateProgressionActivitiesResult
{
    public const OUTCOME_EVALUATED = 'evaluated';

    public const OUTCOME_SKIPPED_PLAN_INACTIVE = 'skipped_plan_inactive';

    public const OUTCOME_DEFERRED_MODULE_PAUSED = 'deferred_module_paused';

    public const OUTCOME_REJECTED_MODULE_INACTIVE = 'rejected_module_inactive';

    /**
     * @param  list<ActivityEvaluation>  $evaluations
     */
    public function __construct(
        public readonly string $outcome,
        public readonly array $evaluations = [],
    ) {}

    public static function skippedPlanInactive(): self
    {
        return new self(self::OUTCOME_SKIPPED_PLAN_INACTIVE);
    }

    public static function deferredModulePaused(): self
    {
        return new self(self::OUTCOME_DEFERRED_MODULE_PAUSED);
    }

    public static function rejectedModuleInactive(): self
    {
        return new self(self::OUTCOME_REJECTED_MODULE_INACTIVE);
    }

    /**
     * @param  list<ActivityEvaluation>  $evaluations
     */
    public static function evaluated(array $evaluations): self
    {
        return new self(self::OUTCOME_EVALUATED, $evaluations);
    }
}
