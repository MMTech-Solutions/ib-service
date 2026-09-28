<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\ListPaymentTemplatesUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListPaymentTemplatesController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, ListPaymentTemplatesUseCase $useCase): JsonResponse
    {
        return $this->success(array_map(static fn ($template): array => $template->toArray(), $useCase->execute()), 'Payment templates retrieved successfully.');
    }
}
