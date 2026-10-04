<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingPageData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Factories\PaymentTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ListPaymentTemplateBindingsCommand;

final class ListPaymentTemplateBindingsUseCase
{
    public function __construct(private readonly PlanRepositoryFactory $plans, private readonly PaymentTemplateBindingRepositoryFactory $bindings) {}

    public function execute(ListPaymentTemplateBindingsCommand $command): PaymentTemplateBindingPageData
    {
        if ($this->plans->make()->findByIdIncludingArchived($command->planId) === null) {
            throw PlanNotFoundException::forId($command->planId);
        }

        return $this->bindings->make()->paginate($command->planId, $command->page, $command->perPage);
    }
}
