<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Ports\Output;

use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesQueryData;
use App\Features\Progression\Contracts\Data\V1\FetchProgressionActivitiesResultData;

interface FetchProgressionActivitiesPort
{
    public function fetch(FetchProgressionActivitiesQueryData $query): FetchProgressionActivitiesResultData;
}
