<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Services\PaymentTemplateCatalogService;

final class ShowPaymentTemplateUseCase
{
    public function __construct(private readonly PaymentTemplateCatalogService $catalog) {}

    public function execute(string $id): PaymentTemplateData
    {
        return $this->catalog->show($id);
    }
}
