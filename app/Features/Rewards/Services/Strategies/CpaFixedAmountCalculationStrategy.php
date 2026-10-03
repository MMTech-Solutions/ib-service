<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Strategies;

use App\Features\Rewards\Contracts\Strategies\CpaRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\DTOs\CpaRewardCalculationResultData;

final class CpaFixedAmountCalculationStrategy implements CpaRewardCalculationStrategyInterface
{
    public function calculate(CpaRewardCalculationInputData $input): CpaRewardCalculationResultData
    {
        $volume = $input->initial_volume;
        foreach ($input->evidence->volume_facts as $fact) {
            $volume = bcadd($volume, $fact->quantity, 8);
        }

        $deposit = $input->initial_deposit_minor;
        foreach ($input->evidence->deposit_facts as $fact) {
            $deposit += $fact['amount_minor'];
        }

        return new CpaRewardCalculationResultData(
            volume: $volume,
            deposit_minor: $deposit,
            qualified: bccomp($volume, $input->required_volume, 8) >= 0
                && $deposit >= $input->required_deposit_minor,
        );
    }
}
