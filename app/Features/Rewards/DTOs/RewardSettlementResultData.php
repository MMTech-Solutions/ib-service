<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class RewardSettlementResultData
{
    public function __construct(
        public string $provider,
        public string $reference_id,
    ) {}
}
