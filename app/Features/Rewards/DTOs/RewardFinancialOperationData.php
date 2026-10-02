<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class RewardFinancialOperationData
{
    public function __construct(
        public string $reward_id,
        public string $operation_id,
        public string $operation_type,
        public string $status,
        public ?string $compensation_reward_id = null,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return ['reward_id' => $this->reward_id, 'operation_id' => $this->operation_id, 'operation_type' => $this->operation_type, 'status' => $this->status, 'compensation_reward_id' => $this->compensation_reward_id];
    }
}
