<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\Services\PaymentTemplateCatalogService;

final class DeletePaymentTemplateUseCase
{
    public function __construct(private readonly PaymentTemplateCatalogService $catalog) {}

    public function execute(string $id, int $lockVersion): void
    {
        $this->catalog->delete($id, $lockVersion);
    }
}
