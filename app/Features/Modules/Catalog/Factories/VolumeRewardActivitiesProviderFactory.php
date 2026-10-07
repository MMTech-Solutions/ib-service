<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Catalog\Contracts\Strategies\VolumeRewardActivitiesProviderStrategyInterface;
use App\Features\Modules\Catalog\Exceptions\UnsupportedRewardEvidenceProviderException;
use App\Features\Modules\Sources\Broker\Services\Strategies\BrokerVolumeRewardActivitiesProviderStrategy;
use App\Features\Modules\Sources\CopyTrading\Services\Strategies\CopyTradingVolumeRewardActivitiesProviderStrategy;
use Illuminate\Contracts\Container\Container;

final class VolumeRewardActivitiesProviderFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $moduleCode): VolumeRewardActivitiesProviderStrategyInterface
    {
        return match ($moduleCode) {
            'broker' => $this->container->make(BrokerVolumeRewardActivitiesProviderStrategy::class),
            'copy_trading' => $this->container->make(CopyTradingVolumeRewardActivitiesProviderStrategy::class),
            default => throw new UnsupportedRewardEvidenceProviderException($moduleCode),
        };
    }
}
