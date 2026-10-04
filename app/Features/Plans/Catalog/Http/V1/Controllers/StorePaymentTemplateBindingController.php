<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\StorePaymentTemplateBindingCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\StorePaymentTemplateBindingRequest;
use App\Features\Plans\Catalog\UseCases\StorePaymentTemplateBindingUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StorePaymentTemplateBindingController
{
    use ApiResponse;

    public function __invoke(StorePaymentTemplateBindingRequest $request, StorePaymentTemplateBindingUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(StorePaymentTemplateBindingCommand::fromRequest($request));

        return $this->success(data: $result->binding->toArray(), message: 'Template version bound successfully.', code: $result->created ? 201 : 200);
    }
}
