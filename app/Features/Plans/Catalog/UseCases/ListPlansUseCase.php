<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Actions\PresentPlanAction;
use App\Features\Plans\Catalog\DTOs\PlansPageData;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ListPlansCommand;
use App\Features\Plans\Catalog\Models\Plan;

final class ListPlansUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly PresentPlanAction $presentPlan,
    ) {}

    public function execute(ListPlansCommand $command): PlansPageData
    {
        $page = $this->repositoryFactory->make()->paginate($command->toQueryData());
        $moduleIds = [];
        foreach ($page->plans as $plan) {
            foreach ($plan->moduleIds() as $moduleId) {
                $moduleIds[] = $moduleId;
            }
        }
        $modules = $this->presentPlan->index(array_values(array_unique($moduleIds)));

        return new PlansPageData(
            plans: array_map(
                static fn (Plan $plan) => $plan->toData($modules),
                $page->plans,
            ),
            currentPage: $page->currentPage,
            perPage: $page->perPage,
            total: $page->total,
            lastPage: $page->lastPage,
        );
    }
}
