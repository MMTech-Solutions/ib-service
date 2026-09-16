<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\AssertProgramBelongsToPlanQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgramSubscriptionContextData;
use App\Features\Programs\Contracts\Data\V1\ResolveFirstProgramByPositionQueryData;

interface ResolveProgramSubscriptionContextPort
{
    public function assertBelongsToPlan(AssertProgramBelongsToPlanQueryData $query): ProgramSubscriptionContextData;

    public function resolveFirstByPosition(ResolveFirstProgramByPositionQueryData $query): ProgramSubscriptionContextData;
}
