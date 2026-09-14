<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\UpdatePlanCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\UpdatePlanRequest;
use App\Features\Plans\Catalog\UseCases\UpdatePlanUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdatePlanController
{
    use ApiResponse;

    public function __invoke(UpdatePlanRequest $request, UpdatePlanUseCase $useCase): JsonResponse
    {
        $plan = $useCase->execute(UpdatePlanCommand::fromRequest($request));

        return $this->success(
            data: ['plan' => $plan->toArray()],
            message: 'Plan updated successfully.',
        );
    }
}
