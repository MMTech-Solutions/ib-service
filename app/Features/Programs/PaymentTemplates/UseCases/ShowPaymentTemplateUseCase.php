<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\ResolvePaymentTemplateAction;
use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;

final class ShowPaymentTemplateUseCase
{
    public function __construct(
        private readonly ResolvePaymentTemplateAction $resolveTemplate,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    public function execute(string $id): PaymentTemplateData
    {
        return $this->transform->execute($this->resolveTemplate->execute($id));
    }
}
