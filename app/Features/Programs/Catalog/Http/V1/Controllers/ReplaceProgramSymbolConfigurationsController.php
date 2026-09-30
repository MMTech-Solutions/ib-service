<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Requests\ReplaceProgramSymbolConfigurationsRequest;
use App\Features\Programs\Catalog\UseCases\ReplaceProgramSymbolConfigurationsUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReplaceProgramSymbolConfigurationsController
{
    use ApiResponse;

    public function __invoke(ReplaceProgramSymbolConfigurationsRequest $request, ReplaceProgramSymbolConfigurationsUseCase $useCase): JsonResponse
    {
        $data = $request->validated();

        return $this->success($useCase->execute((string) $data['plan'], (string) $data['program'], $data['symbols']), 'Program symbol configurations replaced successfully.');
    }
}
