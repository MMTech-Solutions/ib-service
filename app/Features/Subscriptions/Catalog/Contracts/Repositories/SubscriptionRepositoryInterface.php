<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Contracts\Repositories;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionAggregatePageData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionListQueryData;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use Closure;

interface SubscriptionRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?Subscription;

    public function findOpenByExternalUserId(string $externalUserId): ?Subscription;

    public function hasOpenForPlan(string $planId): bool;

    public function resolvePlacementAt(string $subscriptionId, string $occurredAt): ?SubscriptionPlacement;

    public function paginate(SubscriptionListQueryData $query): SubscriptionAggregatePageData;

    public function create(Subscription $subscription): void;

    public function save(Subscription $subscription, int $expectedLockVersion): void;

    public function replace(
        Subscription $outgoing,
        int $expectedOutgoingLockVersion,
        Subscription $incoming,
    ): void;
}
