<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\DeactivatePlanCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\DeactivatePlanRequest;
use App\Features\Plans\Catalog\UseCases\DeactivatePlanUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class DeactivatePlanController
{
    use ApiResponse;

    public function __invoke(
        DeactivatePlanRequest $request,
        DeactivatePlanUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $plan = $useCase->execute(DeactivatePlanCommand::fromRequest($request, $userContext));

        return $this->success(
            data: $plan->toArray(),
            message: 'Plan deactivated successfully.',
        );
    }
}
