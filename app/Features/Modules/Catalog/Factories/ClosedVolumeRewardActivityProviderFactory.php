<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Catalog\Contracts\Strategies\ClosedVolumeRewardActivityProviderStrategyInterface;
use App\Features\Modules\Catalog\Exceptions\UnsupportedRewardEvidenceProviderException;
use App\Features\Modules\Sources\Broker\Services\Strategies\BrokerClosedVolumeRewardActivityProviderStrategy;
use Illuminate\Contracts\Container\Container;

final class ClosedVolumeRewardActivityProviderFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $moduleCode): ClosedVolumeRewardActivityProviderStrategyInterface
    {
        return match ($moduleCode) {
            'broker' => $this->container->make(BrokerClosedVolumeRewardActivityProviderStrategy::class),
            default => throw new UnsupportedRewardEvidenceProviderException($moduleCode),
        };
    }
}
