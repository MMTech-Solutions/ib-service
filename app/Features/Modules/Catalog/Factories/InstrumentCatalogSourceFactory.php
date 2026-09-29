<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Contracts\Exceptions\UnsupportedInstrumentCatalogCapabilityException;
use App\Features\Modules\Sources\Broker\Services\Adapters\BrokerInstrumentCatalogAdapter;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;
use Illuminate\Contracts\Container\Container;

final class InstrumentCatalogSourceFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $moduleId, string $moduleCode): InstrumentCatalogSourceInterface
    {
        return match ($moduleCode) {
            'broker' => $this->container->make(BrokerInstrumentCatalogAdapter::class),
            default => throw UnsupportedInstrumentCatalogCapabilityException::forModule($moduleId),
        };
    }
}
