<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Catalog\Contracts\Strategies\CpaEvidenceProviderStrategyInterface;
use App\Features\Modules\Catalog\Exceptions\UnsupportedRewardEvidenceProviderException;
use App\Features\Modules\Sources\Broker\Services\Strategies\BrokerCpaEvidenceProviderStrategy;
use App\Features\Modules\Sources\CopyTrading\Services\Strategies\CopyTradingCpaEvidenceProviderStrategy;
use Illuminate\Contracts\Container\Container;

final class CpaEvidenceProviderFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $moduleCode): CpaEvidenceProviderStrategyInterface
    {
        return match ($moduleCode) {
            'copy_trading' => $this->container->make(CopyTradingCpaEvidenceProviderStrategy::class),
            'broker' => $this->container->make(BrokerCpaEvidenceProviderStrategy::class),
            default => throw new UnsupportedRewardEvidenceProviderException($moduleCode),
        };
    }

    public function supports(string $moduleCode): bool
    {
        return in_array($moduleCode, ['broker', 'copy_trading'], true);
    }
}
