<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Sources\Broker\Services\Adapters\BrokerVolumeRewardActivityNormalizer;
use App\Features\Modules\Sources\Contracts\VolumeRewardActivityNormalizerInterface;
use App\Features\Modules\Sources\CopyTrading\Services\Adapters\CopyTradingVolumeRewardActivityNormalizer;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class VolumeRewardActivityNormalizerFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $adapter): VolumeRewardActivityNormalizerInterface
    {
        return match ($adapter) {
            'broker_closed_volume_v1' => $this->container->make(BrokerVolumeRewardActivityNormalizer::class),
            'copy_trading_closed_volume_v1' => $this->container->make(CopyTradingVolumeRewardActivityNormalizer::class),
            default => throw new InvalidArgumentException('Unknown volume activity adapter.'),
        };
    }
}
