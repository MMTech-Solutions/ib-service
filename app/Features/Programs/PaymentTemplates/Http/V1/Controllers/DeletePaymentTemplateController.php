<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\DeletePaymentTemplateUseCase;
use Illuminate\Http\JsonResponse;

final class DeletePaymentTemplateController
{
    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, DeletePaymentTemplateUseCase $useCase): JsonResponse
    {
        $useCase->execute($paymentTemplate, (int) $request->validated('lock_version'));

        return response()->json(null, 204);
    }
}
