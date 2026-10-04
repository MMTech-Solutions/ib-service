<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\UseCases;

use App\Features\Programs\Contracts\Data\V1\PaymentTemplateVersionIdentityData;
use App\Features\Programs\Contracts\Ports\Input\ResolvePaymentTemplateVersionPort;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;

final class ResolvePaymentTemplateVersionUseCase implements ResolvePaymentTemplateVersionPort
{
    public function __construct(private readonly PaymentTemplateRepositoryFactory $repositories) {}

    public function execute(string $versionId): ?PaymentTemplateVersionIdentityData
    {
        $version = $this->repositories->make()->findVersion($versionId);

        return $version === null ? null : new PaymentTemplateVersionIdentityData($version->id, $version->templateId, $version->status);
    }
}
