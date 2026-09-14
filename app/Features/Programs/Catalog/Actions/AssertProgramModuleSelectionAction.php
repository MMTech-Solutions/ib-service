<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Actions;

use App\Features\Plans\Contracts\Data\V1\PlanContextData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;

final class AssertProgramModuleSelectionAction
{
    public function __construct(private readonly ResolvePlanContextPort $plans) {}

    /**
     * @param  list<string>  $moduleIds
     */
    public function assertMutable(string $planId, array $moduleIds = []): PlanContextData
    {
        return $this->plans->assertEnabledModuleIds($planId, $moduleIds);
    }
}
