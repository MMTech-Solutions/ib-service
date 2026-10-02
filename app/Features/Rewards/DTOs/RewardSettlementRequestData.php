<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class RewardSettlementRequestData
{
    public function __construct(
        public string $reward_id,
        public string $beneficiary_user_id,
        public int $amount_minor,
        public string $currency_code,
        public int $currency_precision,
        public string $idempotency_key,
    ) {}
}
