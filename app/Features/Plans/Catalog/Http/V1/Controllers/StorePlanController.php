<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\StorePlanCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\StorePlanRequest;
use App\Features\Plans\Catalog\UseCases\StorePlanUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StorePlanController
{
    use ApiResponse;

    public function __invoke(StorePlanRequest $request, StorePlanUseCase $useCase): JsonResponse
    {
        $plan = $useCase->execute(StorePlanCommand::fromRequest($request));

        return $this->created(
            ['plan' => $plan->toArray()],
            'Plan created successfully.',
        );
    }
}
