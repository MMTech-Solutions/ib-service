<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Contracts\Repositories;

use App\Features\Subscriptions\Catalog\DTOs\SubscriptionAggregatePageData;
use App\Features\Subscriptions\Catalog\DTOs\SubscriptionListQueryData;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use App\Features\Subscriptions\Contracts\Data\V1\ProgressionWindowSubscriptionData;
use Closure;

interface SubscriptionRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findById(string $id): ?Subscription;

    public function findOpenByExternalUserId(string $externalUserId): ?Subscription;

    public function hasOpenForPlan(string $planId): bool;

    public function resolvePlacementAt(string $subscriptionId, string $occurredAt): ?SubscriptionPlacement;

    /**
     * Resolves placement intervals that cover `occurredAt` for a beneficiary.
     *
     * Uses the semi-open interval `[effective_from, effective_until)` already
     * owned by each placement (`BR-SUBSCRIPTION-011`).
     *
     * @return list<array{subscription: Subscription, placement: SubscriptionPlacement}>
     */
    public function listPlacementContextsAt(string $externalUserId, string $occurredAt): array;

    public function earliestActivatedAt(): ?string;

    /** @return list<ProgressionWindowSubscriptionData> */
    public function listForProgressionWindow(string $planId, string $windowStartsAt, string $windowEndsAt): array;

    public function paginate(SubscriptionListQueryData $query): SubscriptionAggregatePageData;

    public function create(Subscription $subscription): void;

    public function save(Subscription $subscription, int $expectedLockVersion): void;

    public function replace(
        Subscription $outgoing,
        int $expectedOutgoingLockVersion,
        Subscription $incoming,
    ): void;
}
