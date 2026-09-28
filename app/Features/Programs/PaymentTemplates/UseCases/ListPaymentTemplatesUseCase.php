<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Actions\TransformPaymentTemplateToDataAction;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;

final class ListPaymentTemplatesUseCase
{
    public function __construct(
        private readonly PaymentTemplateRepositoryFactory $repositoryFactory,
        private readonly TransformPaymentTemplateToDataAction $transform,
    ) {}

    /** @return list<PaymentTemplateData> */
    public function execute(): array
    {
        return array_map(
            fn (PaymentTemplate $template): PaymentTemplateData => $this->transform->execute($template),
            $this->repositoryFactory->make()->all(),
        );
    }
}
