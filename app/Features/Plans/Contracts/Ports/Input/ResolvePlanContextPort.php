<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

use App\Features\Plans\Contracts\Data\V1\AssertEnabledModuleIdsQueryData;
use App\Features\Plans\Contracts\Data\V1\PlanContextData;
use App\Features\Plans\Contracts\Data\V1\ResolvePlanContextQueryData;

interface ResolvePlanContextPort
{
    public function resolve(ResolvePlanContextQueryData $query): PlanContextData;

    public function assertEnabledModuleIds(AssertEnabledModuleIdsQueryData $query): PlanContextData;
}
