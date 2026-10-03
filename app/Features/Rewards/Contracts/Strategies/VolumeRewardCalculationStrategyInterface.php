<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Strategies;

use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;

interface VolumeRewardCalculationStrategyInterface
{
    public function calculate(VolumeRewardCalculationData $input): ?PositiveMoney;
}
