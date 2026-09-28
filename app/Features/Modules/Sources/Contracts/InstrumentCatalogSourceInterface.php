<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Contracts;

use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;

interface InstrumentCatalogSourceInterface
{
    public function list(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData;
}
