<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\ShowPaymentTemplateUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowPaymentTemplateController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, ShowPaymentTemplateUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($paymentTemplate)->toArray(), 'Payment template retrieved successfully.');
    }
}
