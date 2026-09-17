<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveSubscriptionContextQueryData extends Data
{
    public function __construct(
        public readonly string $external_user_id,
        public readonly string $occurred_at,
    ) {}
}
