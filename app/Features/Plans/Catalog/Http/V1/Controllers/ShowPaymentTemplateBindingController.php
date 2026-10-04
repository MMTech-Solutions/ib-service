<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\Http\V1\Commands\ShowPaymentTemplateBindingCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ShowPaymentTemplateBindingRequest;
use App\Features\Plans\Catalog\UseCases\ShowPaymentTemplateBindingUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowPaymentTemplateBindingController
{
    use ApiResponse;

    public function __invoke(ShowPaymentTemplateBindingRequest $request, ShowPaymentTemplateBindingUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ShowPaymentTemplateBindingCommand::fromRequest($request));

        return $this->success($result->toArray(), 'Template binding retrieved successfully.');
    }
}
