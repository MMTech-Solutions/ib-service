<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Strategies;

use App\Features\Rewards\DTOs\NegativePnlRewardCalculationData;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;

interface NegativePnlRewardCalculationStrategyInterface
{
    public function calculate(NegativePnlRewardCalculationData $input): ?PositiveMoney;
}
