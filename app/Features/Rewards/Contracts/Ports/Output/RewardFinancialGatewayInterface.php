<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Output;

use App\Features\Rewards\DTOs\FinanceCommissionEventData;
use App\Features\Rewards\DTOs\RewardReversalRequestData;
use App\Features\Rewards\DTOs\RewardSettlementResultData;

interface RewardFinancialGatewayInterface
{
    public function reverse(RewardReversalRequestData $request): RewardSettlementResultData;

    public function findByIdempotencyKey(string $idempotencyKey): ?FinanceCommissionEventData;
}
