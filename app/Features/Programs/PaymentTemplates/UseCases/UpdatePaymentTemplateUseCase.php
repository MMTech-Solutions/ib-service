<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Services\PaymentTemplateCatalogService;

final class UpdatePaymentTemplateUseCase
{
    public function __construct(private readonly PaymentTemplateCatalogService $catalog) {}

    public function execute(string $id, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        return $this->catalog->update($id, $command);
    }
}
