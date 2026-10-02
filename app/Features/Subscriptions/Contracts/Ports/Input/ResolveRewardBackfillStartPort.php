<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Ports\Input;

use App\Features\Subscriptions\Contracts\Data\V1\ResolveRewardBackfillStartData;

interface ResolveRewardBackfillStartPort
{
    public function execute(): ResolveRewardBackfillStartData;
}
