<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class RewardReversalRequestData
{
    public function __construct(
        public string $reward_id,
        public string $beneficiary_user_id,
        public int $amount_minor,
        public string $currency_code,
        public int $currency_precision,
        public string $idempotency_key,
        public string $original_finance_event_id,
        public string $reason_code,
        public ?string $reason_label,
    ) {}
}
