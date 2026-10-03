<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Strategies;

use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\DTOs\CpaRewardCalculationResultData;

interface CpaRewardCalculationStrategyInterface
{
    public function calculate(CpaRewardCalculationInputData $input): CpaRewardCalculationResultData;
}
