<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\StorePaymentTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StorePaymentTemplateVersionController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, StorePaymentTemplateVersionUseCase $useCase): JsonResponse
    {
        return $this->created($useCase->execute($paymentTemplate, ManagePaymentTemplateCommand::fromRequest($request))->toArray(), 'Payment template version created successfully.');
    }
}
