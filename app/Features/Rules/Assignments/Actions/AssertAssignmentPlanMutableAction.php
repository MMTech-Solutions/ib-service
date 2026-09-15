<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\Actions;

use App\Features\Plans\Contracts\Data\V1\AssertEnabledModuleIdsQueryData;
use App\Features\Plans\Contracts\Data\V1\PlanContextData;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;

final class AssertAssignmentPlanMutableAction
{
    public function __construct(private readonly ResolvePlanContextPort $plans) {}

    public function assertMutable(string $planId): PlanContextData
    {
        return $this->plans->assertEnabledModuleIds(new AssertEnabledModuleIdsQueryData(
            plan_id: $planId,
        ));
    }
}
