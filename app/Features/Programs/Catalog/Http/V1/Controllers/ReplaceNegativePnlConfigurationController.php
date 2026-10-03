<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\ReplaceNegativePnlConfigurationCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\ReplaceNegativePnlConfigurationRequest;
use App\Features\Programs\Catalog\Http\V1\Resources\NegativePnlConfigurationResource;
use App\Features\Programs\Catalog\UseCases\ReplaceNegativePnlConfigurationUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReplaceNegativePnlConfigurationController
{
    use ApiResponse;

    public function __invoke(ReplaceNegativePnlConfigurationRequest $request, ReplaceNegativePnlConfigurationUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ReplaceNegativePnlConfigurationCommand::fromRequest($request));

        return $this->success($result === null ? [] : (new NegativePnlConfigurationResource($result))->resolve($request), 'Negative PnL configuration replaced successfully.');
    }
}
