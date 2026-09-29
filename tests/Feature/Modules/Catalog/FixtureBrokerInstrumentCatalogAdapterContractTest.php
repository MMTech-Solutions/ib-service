<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Features\Modules\Sources\Broker\Services\Adapters\FixtureBrokerInstrumentCatalogAdapter;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;
use Tests\Contracts\InstrumentCatalogSourceContract;

final class FixtureBrokerInstrumentCatalogAdapterContractTest extends InstrumentCatalogSourceContract
{
    protected function source(): InstrumentCatalogSourceInterface
    {
        return new FixtureBrokerInstrumentCatalogAdapter;
    }
}
