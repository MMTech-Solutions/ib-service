<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Contracts\Strategies\VolumeRewardCalculationStrategyInterface;
use App\Features\Rewards\Exceptions\UnsupportedVolumeRewardCalculationStrategyException;
use App\Features\Rewards\Services\Strategies\TradedVolumeCommissionCalculationStrategy;
use Illuminate\Contracts\Container\Container;

final class VolumeRewardCalculationStrategyFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $strategyType): VolumeRewardCalculationStrategyInterface
    {
        return match ($strategyType) {
            'traded_volume_commission' => $this->container->make(TradedVolumeCommissionCalculationStrategy::class),
            default => throw new UnsupportedVolumeRewardCalculationStrategyException($strategyType),
        };
    }
}
