<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Services\PaymentTemplateCatalogService;

final class PublishPaymentTemplateVersionUseCase
{
    public function __construct(private readonly PaymentTemplateCatalogService $catalog) {}

    public function execute(string $id, string $versionId, int $lockVersion): PaymentTemplateData
    {
        return $this->catalog->publishVersion($id, $versionId, $lockVersion);
    }
}
