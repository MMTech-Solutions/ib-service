<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

use App\Features\Plans\Contracts\Data\V1\PlanProgressionContextData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanProgressionContextQueryData;

interface ResolvePlanProgressionContextPort
{
    public function resolve(ResolvePlanProgressionContextQueryData $query): PlanProgressionContextData;
}
