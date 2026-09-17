<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Ports\Input;

use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextQueryData;
use App\Features\Rules\Contracts\Data\V1\ResolvePointsContributionContextResultData;

interface ResolvePointsContributionContextPort
{
    public function resolve(ResolvePointsContributionContextQueryData $query): ResolvePointsContributionContextResultData;
}
