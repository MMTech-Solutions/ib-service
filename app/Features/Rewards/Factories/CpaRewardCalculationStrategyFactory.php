<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Contracts\Strategies\CpaRewardCalculationStrategyInterface;
use App\Features\Rewards\Exceptions\UnsupportedCpaRewardCalculationStrategyException;
use App\Features\Rewards\Services\Strategies\CpaFixedAmountCalculationStrategy;
use Illuminate\Contracts\Container\Container;

final class CpaRewardCalculationStrategyFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $strategyType): CpaRewardCalculationStrategyInterface
    {
        return match ($strategyType) {
            'cpa_fixed_amount' => $this->container->make(CpaFixedAmountCalculationStrategy::class),
            default => throw new UnsupportedCpaRewardCalculationStrategyException($strategyType),
        };
    }
}
