<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\PublishPaymentTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class PublishPaymentTemplateVersionController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, string $version, PublishPaymentTemplateVersionUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($paymentTemplate, $version, (int) $request->validated('lock_version'))->toArray(), 'Payment template version published successfully.');
    }
}
