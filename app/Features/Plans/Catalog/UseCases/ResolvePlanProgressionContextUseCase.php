<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Data\V1\PlanProgressionContextData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanProgressionContextQueryData;
use App\Features\Plans\Contracts\Exceptions\PlanNotFoundException;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;

final class ResolvePlanProgressionContextUseCase implements ResolvePlanProgressionContextPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function resolve(ResolvePlanProgressionContextQueryData $query): PlanProgressionContextData
    {
        $repository = $this->repositoryFactory->make();
        $plan = $repository->findByIdIncludingArchived($query->plan_id);
        if ($plan === null) {
            throw PlanNotFoundException::forId($query->plan_id);
        }

        $change = $repository->findLastOperationalChangeAtOrBefore($plan->id, $query->occurred_at);
        $isActive = $change?->nextIsActive ?? false;

        return new PlanProgressionContextData(
            plan_id: $plan->id,
            is_active: $isActive,
            progression_period: $plan->progressionPeriod->value,
        );
    }
}
