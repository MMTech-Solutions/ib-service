<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\ShowNegativePnlConfigurationCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\ShowProgramRequest;
use App\Features\Programs\Catalog\Http\V1\Resources\NegativePnlConfigurationResource;
use App\Features\Programs\Catalog\UseCases\ShowNegativePnlConfigurationUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowNegativePnlConfigurationController
{
    use ApiResponse;

    public function __invoke(ShowProgramRequest $request, ShowNegativePnlConfigurationUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ShowNegativePnlConfigurationCommand::fromRequest($request));

        return $this->success($result === null ? [] : (new NegativePnlConfigurationResource($result))->resolve($request), 'Negative PnL configuration retrieved successfully.');
    }
}
