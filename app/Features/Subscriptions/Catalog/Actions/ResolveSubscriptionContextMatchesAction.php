<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Actions;

use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use App\Features\Subscriptions\Contracts\Data\V1\SubscriptionContextData;

final class ResolveSubscriptionContextMatchesAction
{
    public function __construct(
        private readonly SubscriptionRepositoryFactory $repositoryFactory,
    ) {}

    /**
     * @return list<SubscriptionContextData>
     */
    public function matchesAt(string $externalUserId, string $occurredAt): array
    {
        $matches = $this->repositoryFactory->make()->listPlacementContextsAt(
            $externalUserId,
            $occurredAt,
        );

        return array_map(
            static fn (array $match): SubscriptionContextData => self::toContextData(
                $match['subscription'],
                $match['placement'],
            ),
            $matches,
        );
    }

    private static function toContextData(
        Subscription $subscription,
        SubscriptionPlacement $placement,
    ): SubscriptionContextData {
        return new SubscriptionContextData(
            subscription_id: $subscription->id,
            plan_id: $subscription->planId,
            program_id: $placement->programId,
            placement_id: $placement->id,
            placement_condition: $placement->condition->value,
        );
    }
}
