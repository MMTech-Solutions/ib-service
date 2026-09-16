<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionDetailData extends Data
{
    /**
     * @param  list<SubscriptionChangeData>  $changes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $external_user_id,
        public readonly string $plan_id,
        public readonly string $origin,
        public readonly ?bool $requires_approval,
        public readonly string $status,
        public readonly ?string $activated_at,
        public readonly ?string $closed_at,
        public readonly ?string $replaces_subscription_id,
        public readonly int $lock_version,
        public readonly ?SubscriptionPlacementData $current_placement,
        public readonly array $changes,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
