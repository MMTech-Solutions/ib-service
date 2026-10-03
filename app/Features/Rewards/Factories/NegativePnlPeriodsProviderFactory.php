<?php

declare(strict_types=1);

namespace App\Features\Rewards\Factories;

use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\UnsupportedNegativePnlPeriodsProviderException;
use App\Features\Rewards\Services\Adapters\BrokerResolveNegativePnlPeriodsAdapter;
use Illuminate\Contracts\Container\Container;

final class NegativePnlPeriodsProviderFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $providerCode): ResolveNegativePnlPeriodsPort
    {
        return match ($providerCode) {
            'broker' => $this->container->make(BrokerResolveNegativePnlPeriodsAdapter::class),
            default => throw new UnsupportedNegativePnlPeriodsProviderException($providerCode),
        };
    }
}
