<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Data\V1\HasOpenSubscriptionsForPlanQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;

final class HasOpenSubscriptionsForPlanUseCase implements HasOpenSubscriptionsForPlanPort
{
    public function __construct(private readonly SubscriptionRepositoryFactory $repositoryFactory) {}

    public function hasOpen(HasOpenSubscriptionsForPlanQueryData $query): bool
    {
        return $this->repositoryFactory->make()->hasOpenForPlan($query->plan_id);
    }
}
