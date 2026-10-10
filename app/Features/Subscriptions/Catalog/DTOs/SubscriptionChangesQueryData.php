<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionChangesQueryData extends Data
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $action = null,
        public readonly ?string $actorKind = null,
        public readonly ?string $occurredAtFrom = null,
        public readonly ?string $occurredAtTo = null,
    ) {}
}
