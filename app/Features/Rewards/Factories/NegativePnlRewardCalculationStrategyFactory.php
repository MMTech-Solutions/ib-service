<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Contracts\Strategies\NegativePnlRewardCalculationStrategyInterface;
use App\Features\Rewards\Exceptions\UnsupportedNegativePnlRewardCalculationStrategyException;
use App\Features\Rewards\Services\Strategies\NegativePnlShareCalculationStrategy;
use Illuminate\Contracts\Container\Container;

final class NegativePnlRewardCalculationStrategyFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $strategyType): NegativePnlRewardCalculationStrategyInterface
    {
        return match ($strategyType) {
            'negative_pnl_share' => $this->container->make(NegativePnlShareCalculationStrategy::class), default => throw new UnsupportedNegativePnlRewardCalculationStrategyException($strategyType),
        };
    }
}
