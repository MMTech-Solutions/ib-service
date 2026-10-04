<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Exceptions\TemplateBindingException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Factories\ProgressionTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ShowProgressionTemplateBindingCommand;

final class ShowProgressionTemplateBindingUseCase
{
    public function __construct(private readonly PlanRepositoryFactory $plans, private readonly ProgressionTemplateBindingRepositoryFactory $bindings) {}

    public function execute(ShowProgressionTemplateBindingCommand $command): ProgressionTemplateBindingData
    {
        if ($this->plans->make()->findByIdIncludingArchived($command->planId) === null) {
            throw PlanNotFoundException::forId($command->planId);
        }

        return $this->bindings->make()->find($command->planId, $command->bindingId) ?? throw TemplateBindingException::bindingNotFound($command->bindingId);
    }
}
