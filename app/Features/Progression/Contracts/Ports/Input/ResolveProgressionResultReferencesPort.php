<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Ports\Input;

use App\Features\Progression\Contracts\Data\V1\ProgressionResultReferenceData;
use App\Features\Progression\Contracts\Data\V1\ResolveProgressionResultReferencesQueryData;

interface ResolveProgressionResultReferencesPort
{
    /** @return list<ProgressionResultReferenceData> */
    public function execute(ResolveProgressionResultReferencesQueryData $query): array;
}
