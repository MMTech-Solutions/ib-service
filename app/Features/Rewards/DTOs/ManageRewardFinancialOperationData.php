<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class ManageRewardFinancialOperationData
{
    public function __construct(
        public string $reward_id,
        public string $actor_user_id,
        public string $operation_type,
        public string $reason_code,
        public ?string $reason_label = null,
        public ?int $amount_minor = null,
        public ?string $idempotency_key = null,
    ) {}
}
