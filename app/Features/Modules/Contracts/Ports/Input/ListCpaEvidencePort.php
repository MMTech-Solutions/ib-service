<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;

interface ListCpaEvidencePort
{
    public function list(ListCpaEvidenceQueryData $query): CpaEvidenceData;
}
