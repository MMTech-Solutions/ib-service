<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\UpdatePaymentTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdatePaymentTemplateVersionController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, string $version, UpdatePaymentTemplateVersionUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($paymentTemplate, $version, ManagePaymentTemplateCommand::fromRequest($request))->toArray(), 'Payment template version updated successfully.');
    }
}
