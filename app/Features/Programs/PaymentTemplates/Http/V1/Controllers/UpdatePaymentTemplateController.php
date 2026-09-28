<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\UpdatePaymentTemplateUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdatePaymentTemplateController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, UpdatePaymentTemplateUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($paymentTemplate, ManagePaymentTemplateCommand::fromRequest($request))->toArray(), 'Payment template updated successfully.');
    }
}
