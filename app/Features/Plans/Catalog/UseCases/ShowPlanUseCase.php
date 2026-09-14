<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Actions\PresentPlanAction;
use App\Features\Plans\Catalog\DTOs\PlanDetailData;
use App\Features\Plans\Catalog\Exceptions\PlanNotFoundException;
use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Catalog\Http\V1\Commands\ShowPlanCommand;

final class ShowPlanUseCase
{
    public function __construct(
        private readonly PlanRepositoryFactory $repositoryFactory,
        private readonly PresentPlanAction $presentPlan,
    ) {}

    public function execute(ShowPlanCommand $command): PlanDetailData
    {
        $plan = $this->repositoryFactory->make()->findById($command->planId);
        if ($plan === null) {
            throw PlanNotFoundException::forId($command->planId);
        }

        return $this->presentPlan->toDetail($plan);
    }
}
