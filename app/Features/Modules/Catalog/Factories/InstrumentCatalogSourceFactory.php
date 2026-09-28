<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Factories;

use App\Features\Modules\Sources\Broker\Services\Adapters\FixtureBrokerInstrumentCatalogAdapter;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class InstrumentCatalogSourceFactory
{
    public function __construct(private readonly Container $container) {}

    public function make(string $moduleCode): InstrumentCatalogSourceInterface
    {
        return match ($moduleCode) {
            'broker' => $this->container->make(FixtureBrokerInstrumentCatalogAdapter::class),
            default => throw new InvalidArgumentException("Instrument catalogue is not available for module [{$moduleCode}]."),
        };
    }
}
