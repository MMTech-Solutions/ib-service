<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

use App\Features\Plans\Contracts\Data\V1\PlanSubscriptionContextData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanSubscriptionContextQueryData;

interface ResolvePlanSubscriptionContextPort
{
    public function resolve(ResolvePlanSubscriptionContextQueryData $query): PlanSubscriptionContextData;
}
