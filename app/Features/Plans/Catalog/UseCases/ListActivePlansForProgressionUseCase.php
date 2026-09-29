<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Data\V1\ProgressionPlanData;
use App\Features\Plans\Contracts\Ports\Input\ListActivePlansForProgressionPort;

final class ListActivePlansForProgressionUseCase implements ListActivePlansForProgressionPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function list(): array
    {
        return array_map(
            static fn ($plan): ProgressionPlanData => new ProgressionPlanData(
                plan_id: $plan->id,
                progression_period: $plan->progressionPeriod->value,
            ),
            $this->repositoryFactory->make()->allActive(),
        );
    }
}
