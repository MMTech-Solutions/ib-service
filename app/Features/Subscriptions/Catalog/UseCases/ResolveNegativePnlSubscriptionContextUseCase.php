<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Actions\ResolveSubscriptionContextMatchesAction;
use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Contracts\Data\V1\SubscriptionContextData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveNegativePnlSubscriptionContextPort;

final class ResolveNegativePnlSubscriptionContextUseCase implements ResolveNegativePnlSubscriptionContextPort
{
    public function __construct(private readonly SubscriptionRepositoryFactory $repositories) {}

    public function execute(string $subscriptionId, string $occurredAt): ?SubscriptionContextData
    {
        $repository = $this->repositories->make();
        $subscription = $repository->findById($subscriptionId);
        $placement = $subscription === null ? null : $repository->resolvePlacementAt($subscriptionId, $occurredAt);

        return $subscription === null || $placement === null ? null : ResolveSubscriptionContextMatchesAction::toContextData($subscription, $placement, $occurredAt);
    }
}
