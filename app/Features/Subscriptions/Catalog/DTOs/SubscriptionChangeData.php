<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class SubscriptionChangeData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $operation_id,
        public readonly string $action,
        public readonly string $actor_kind,
        public readonly ?string $actor_external_user_id,
        public readonly ?string $reason,
        public readonly ?string $previous_status,
        public readonly ?string $next_status,
        public readonly ?string $previous_program_id,
        public readonly ?string $next_program_id,
        public readonly ?bool $previous_is_fixed,
        public readonly ?bool $next_is_fixed,
        public readonly string $occurred_at,
    ) {}
}
