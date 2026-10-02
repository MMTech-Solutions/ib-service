<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Output;

use App\Features\Rewards\DTOs\RewardSettlementRequestData;
use App\Features\Rewards\DTOs\RewardSettlementResultData;

interface RewardSettlementGatewayInterface
{
    public function settle(RewardSettlementRequestData $request): RewardSettlementResultData;
}
