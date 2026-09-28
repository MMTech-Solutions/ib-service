<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Controllers;

use App\Features\Programs\PaymentTemplates\Http\V1\Requests\ManagePaymentTemplateRequest;
use App\Features\Programs\PaymentTemplates\UseCases\DeletePaymentTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;

final class DeletePaymentTemplateVersionController
{
    public function __invoke(ManagePaymentTemplateRequest $request, string $paymentTemplate, string $version, DeletePaymentTemplateVersionUseCase $useCase): JsonResponse
    {
        $useCase->execute($paymentTemplate, $version, (int) $request->validated('lock_version'));

        return response()->json(null, 204);
    }
}
