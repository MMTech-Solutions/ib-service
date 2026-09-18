<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Controllers;

use App\Features\Progression\DTOs\ActivityEvaluationData;
use App\Features\Progression\Http\V1\Commands\ListActivityEvaluationsCommand;
use App\Features\Progression\Http\V1\Requests\ListActivityEvaluationsRequest;
use App\Features\Progression\UseCases\ListActivityEvaluationsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListActivityEvaluationsController
{
    use ApiResponse;

    public function __invoke(
        ListActivityEvaluationsRequest $request,
        ListActivityEvaluationsUseCase $useCase,
    ): JsonResponse {
        $command = ListActivityEvaluationsCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $evaluations = array_map(
            static fn (ActivityEvaluationData $evaluation): array => $evaluation->toArray(),
            $result->evaluations,
        );
        $paginator = new LengthAwarePaginator(
            items: $evaluations,
            total: $result->total,
            perPage: $result->perPage,
            currentPage: $result->currentPage,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );
        $filters = array_filter(
            $request->safe()->only([
                'plan_id',
                'subscription_id',
                'status',
                'exclusion_reason',
                'occurred_at_from',
                'occurred_at_to',
            ]),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $this->success(
            data: $evaluations,
            message: 'Activity evaluations retrieved successfully.',
            meta: ['filters' => (object) $filters],
            paginator: $paginator,
        );
    }
}
