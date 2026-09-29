<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Repositories;

use App\Features\Progression\DTOs\ActivityEvaluationAggregatePageData;
use App\Features\Progression\DTOs\ActivityEvaluationListQueryData;
use App\Features\Progression\Models\ActivityEvaluation;
use Closure;

interface ActivityEvaluationRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?ActivityEvaluation;

    public function findByIdempotencyKey(
        string $moduleId,
        string $sourceActivityId,
        string $beneficiaryExternalUserId,
        ?int $distributionLevel = null,
    ): ?ActivityEvaluation;

    public function paginate(ActivityEvaluationListQueryData $query): ActivityEvaluationAggregatePageData;

    /**
     * Persiste de forma atómica una evaluación y su contribución opcional.
     * Una colisión del índice de idempotencia devuelve el resultado canónico
     * sin recalcular.
     */
    public function record(ActivityEvaluation $evaluation): ActivityEvaluation;
}
