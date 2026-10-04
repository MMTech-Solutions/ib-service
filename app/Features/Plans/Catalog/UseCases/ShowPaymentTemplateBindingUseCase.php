<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Exceptions\TemplateBindingException;
use App\Features\Plans\Catalog\Factories\PaymentTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ShowPaymentTemplateBindingCommand;

final class ShowPaymentTemplateBindingUseCase
{
    public function __construct(private readonly PlanRepositoryFactory $plans, private readonly PaymentTemplateBindingRepositoryFactory $bindings) {}

    public function execute(ShowPaymentTemplateBindingCommand $command): PaymentTemplateBindingData
    {
        if ($this->plans->make()->findByIdIncludingArchived($command->planId) === null) {
            throw PlanNotFoundException::forId($command->planId);
        }

        return $this->bindings->make()->find($command->planId, $command->bindingId) ?? throw TemplateBindingException::bindingNotFound($command->bindingId);
    }
}
