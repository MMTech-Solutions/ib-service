<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PaymentTemplateBindingRepositoryFactory;
use App\Features\Plans\Contracts\Data\V1\ResolvePaymentTemplateBindingQueryData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePaymentTemplateBindingPort;

final class ResolvePaymentTemplateBindingUseCase implements ResolvePaymentTemplateBindingPort
{
    public function __construct(private readonly PaymentTemplateBindingRepositoryFactory $repositories) {}

    public function execute(ResolvePaymentTemplateBindingQueryData $query): ?string
    {
        return $this->repositories->make()->resolve($query->plan_id, $query->binding_id);
    }
}
