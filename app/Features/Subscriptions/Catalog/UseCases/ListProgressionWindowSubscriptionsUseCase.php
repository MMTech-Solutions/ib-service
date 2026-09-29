<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Data\V1\ListProgressionWindowSubscriptionsQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ListProgressionWindowSubscriptionsPort;

final class ListProgressionWindowSubscriptionsUseCase implements ListProgressionWindowSubscriptionsPort
{
    public function __construct(private readonly SubscriptionRepositoryFactory $repositoryFactory) {}

    public function list(ListProgressionWindowSubscriptionsQueryData $query): array
    {
        return $this->repositoryFactory->make()->listForProgressionWindow(
            $query->plan_id,
            $query->window_starts_at,
            $query->window_ends_at,
        );
    }
}
