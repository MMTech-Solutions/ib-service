<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\StoreProgressionTemplateBindingCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\StoreProgressionTemplateBindingRequest;
use App\Features\Plans\Catalog\UseCases\StoreProgressionTemplateBindingUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StoreProgressionTemplateBindingController
{
    use ApiResponse;

    public function __invoke(StoreProgressionTemplateBindingRequest $request, StoreProgressionTemplateBindingUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(StoreProgressionTemplateBindingCommand::fromRequest($request));

        return $this->success(data: $result->binding->toArray(), message: 'Template version bound successfully.', code: $result->created ? 201 : 200);
    }
}
