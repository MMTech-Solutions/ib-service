<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Actions;

use App\Features\Subscriptions\Catalog\Factories\SubscriptionRepositoryFactory;
use App\Features\Subscriptions\Catalog\Models\Subscription;
use App\Features\Subscriptions\Catalog\Models\SubscriptionPlacement;
use App\Features\Subscriptions\Contracts\Data\V1\SubscriptionContextData;
use Carbon\CarbonImmutable;

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
                $occurredAt,
            ),
            $matches,
        );
    }

    public static function toContextData(
        Subscription $subscription,
        SubscriptionPlacement $placement,
        string $occurredAt,
    ): SubscriptionContextData {
        [$personalRate, $isMaster, $masterRate] = self::rewardRatesAt($subscription, $occurredAt);

        return new SubscriptionContextData(
            subscription_id: $subscription->id,
            plan_id: $subscription->planId,
            program_id: $placement->programId,
            placement_id: $placement->id,
            placement_condition: $placement->condition->value,
            activated_at: (string) $subscription->activatedAt,
            personal_rate: $personalRate,
            is_master: $isMaster,
            master_rate: $masterRate,
        );
    }

    /** @return array{0: string, 1: bool, 2: string} */
    private static function rewardRatesAt(Subscription $subscription, string $occurredAt): array
    {
        $at = CarbonImmutable::parse($occurredAt)->utc();
        $personalRate = $subscription->personalRate;
        $isMaster = $subscription->isMaster;
        $masterRate = $subscription->masterRate;
        $changes = $subscription->changes;

        usort($changes, static function ($left, $right): int {
            $occurredCompare = strcmp($right->occurredAt, $left->occurredAt);

            return $occurredCompare !== 0 ? $occurredCompare : strcmp($right->id, $left->id);
        });

        foreach ($changes as $change) {
            if (! CarbonImmutable::parse($change->occurredAt)->utc()->gt($at)
                || $change->previousPersonalRate === null
                || $change->previousIsMaster === null
                || $change->previousMasterRate === null) {
                continue;
            }

            $personalRate = $change->previousPersonalRate;
            $isMaster = $change->previousIsMaster;
            $masterRate = $change->previousMasterRate;
        }

        return [$personalRate, $isMaster, $masterRate];
    }
}
