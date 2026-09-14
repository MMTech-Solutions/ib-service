<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\ActivatePlanCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ActivatePlanRequest;
use App\Features\Plans\Catalog\UseCases\ActivatePlanUseCase;
use App\SharedFeatures\User\Context\UserContext;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ActivatePlanController
{
    use ApiResponse;

    public function __invoke(
        ActivatePlanRequest $request,
        ActivatePlanUseCase $useCase,
        UserContext $userContext,
    ): JsonResponse {
        $plan = $useCase->execute(ActivatePlanCommand::fromRequest($request, $userContext));

        return $this->success(
            data: ['plan' => $plan->toArray()],
            message: 'Plan activated successfully.',
        );
    }
}
