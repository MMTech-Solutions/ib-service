<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\UseCases;

use App\Features\Plans\Catalog\Factories\PlanRepositoryFactory;
use App\Features\Plans\Contracts\Data\V1\PlanSubscriptionContextData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;
use App\Features\Plans\Contracts\Exceptions\PlanNotFoundException;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;

final class ResolvePlanSubscriptionContextUseCase implements ResolvePlanSubscriptionContextPort
{
    public function __construct(private readonly PlanRepositoryFactory $repositoryFactory) {}

    public function resolve(ResolvePlanSubscriptionContextQueryData $query): PlanSubscriptionContextData
    {
        $plan = $this->repositoryFactory->make()->findByIdIncludingArchived($query->plan_id);
        if ($plan === null) {
            throw PlanNotFoundException::forId($query->plan_id);
        }

        return new PlanSubscriptionContextData(
            id: $plan->id,
            is_active: $plan->isActive,
            archived: $plan->deletedAt !== null,
            requires_approval: $plan->requiresApproval,
        );
    }
}
