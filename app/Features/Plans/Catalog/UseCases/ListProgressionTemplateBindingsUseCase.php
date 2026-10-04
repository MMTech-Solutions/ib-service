<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingPageData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Factories\ProgressionTemplateBindingRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ListProgressionTemplateBindingsCommand;

final class ListProgressionTemplateBindingsUseCase
{
    public function __construct(private readonly PlanRepositoryFactory $plans, private readonly ProgressionTemplateBindingRepositoryFactory $bindings) {}

    public function execute(ListProgressionTemplateBindingsCommand $command): ProgressionTemplateBindingPageData
    {
        if ($this->plans->make()->findByIdIncludingArchived($command->planId) === null) {
            throw PlanNotFoundException::forId($command->planId);
        }

        return $this->bindings->make()->paginate($command->planId, $command->page, $command->perPage);
    }
}
