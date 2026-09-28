<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Services\PaymentTemplateCatalogService;

final class UpdatePaymentTemplateVersionUseCase
{
    public function __construct(private readonly PaymentTemplateCatalogService $catalog) {}

    public function execute(string $id, string $versionId, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        return $this->catalog->updateVersion($id, $versionId, $command);
    }
}
