<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;

interface ListInstrumentCatalogPort
{
    public function execute(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData;
}
