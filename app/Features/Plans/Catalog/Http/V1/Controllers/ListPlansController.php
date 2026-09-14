<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\DTOs\PlanData;
use App\Features\Plans\Catalog\Http\V1\Commands\ListPlansCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ListPlansRequest;
use App\Features\Plans\Catalog\UseCases\ListPlansUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListPlansController
{
    use ApiResponse;

    public function __invoke(ListPlansRequest $request, ListPlansUseCase $useCase): JsonResponse
    {
        $command = ListPlansCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $plans = array_map(static fn (PlanData $plan): array => $plan->toArray(), $result->plans);
        $paginator = new LengthAwarePaginator(
            items: $plans,
            total: $result->total,
            perPage: $result->perPage,
            currentPage: $result->currentPage,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );
        $filters = array_filter(
            $request->safe()->only(['search', 'is_active']),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $this->success(
            data: $plans,
            message: 'Plans retrieved successfully.',
            meta: ['filters' => (object) $filters],
            paginator: $paginator,
        );
    }
}
