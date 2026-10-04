<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\ShowProgressionTemplateBindingCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ShowProgressionTemplateBindingRequest;
use App\Features\Plans\Catalog\UseCases\ShowProgressionTemplateBindingUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowProgressionTemplateBindingController
{
    use ApiResponse;

    public function __invoke(ShowProgressionTemplateBindingRequest $request, ShowProgressionTemplateBindingUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ShowProgressionTemplateBindingCommand::fromRequest($request));

        return $this->success($result->toArray(), 'Template binding retrieved successfully.');
    }
}
