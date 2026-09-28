<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\StorePaymentTemplateUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StorePaymentTemplateController
{
    use ApiResponse;

    public function __invoke(ManagePaymentTemplateRequest $request, StorePaymentTemplateUseCase $useCase): JsonResponse
    {
        return $this->created($useCase->execute(ManagePaymentTemplateCommand::fromRequest($request))->toArray(), 'Payment template created successfully.');
    }
}
