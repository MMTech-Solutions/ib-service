<?php

declare(strict_types=1);

namespace App\Features\Rewards\Exceptions;

use RuntimeException;

final class UnsupportedVolumeRewardCalculationStrategyException extends RuntimeException
{
    public function __construct(public readonly string $strategy_type)
    {
        parent::__construct("Unsupported volume reward calculation strategy [{$strategy_type}].");
    }
}
