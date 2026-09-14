<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\ShowPlanCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ShowPlanRequest;
use App\Features\Plans\Catalog\UseCases\ShowPlanUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowPlanController
{
    use ApiResponse;

    public function __invoke(ShowPlanRequest $request, ShowPlanUseCase $useCase): JsonResponse
    {
        $plan = $useCase->execute(ShowPlanCommand::fromRequest($request));

        return $this->success(
            data: ['plan' => $plan->toArray()],
            message: 'Plan retrieved successfully.',
        );
    }
}
