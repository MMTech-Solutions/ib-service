<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Output;

use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveNegativePnlPeriodsResultData;

interface ResolveNegativePnlPeriodsPort
{
    public function resolve(ResolveNegativePnlPeriodsQueryData $query): ResolveNegativePnlPeriodsResultData;
}
