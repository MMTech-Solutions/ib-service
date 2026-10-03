<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Strategies;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;

interface CpaEvidenceProviderStrategyInterface
{
    public function fetch(ListCpaEvidenceQueryData $query): CpaEvidenceData;
}
