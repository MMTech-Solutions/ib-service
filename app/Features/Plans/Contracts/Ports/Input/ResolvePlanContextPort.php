<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

use App\Features\Plans\Contracts\Data\V1\PlanContextData;

interface ResolvePlanContextPort
{
    public function resolve(string $planId): PlanContextData;

    /**
     * @param  list<string>  $moduleIds
     */
    public function assertEnabledModuleIds(string $planId, array $moduleIds): PlanContextData;
}
