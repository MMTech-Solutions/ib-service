<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use App\Features\Subscriptions\Catalog\Enums\SubscriptionStatus;
use Spatie\LaravelData\Data;

final class SubscriptionListQueryData extends Data
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $planId = null,
        public readonly ?SubscriptionStatus $status = null,
        public readonly ?string $externalUserId = null,
    ) {}
}
