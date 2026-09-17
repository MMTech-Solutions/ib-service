<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Ports\Input;

use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextResultData;

interface ResolveSubscriptionContextPort
{
    public function resolve(ResolveSubscriptionContextQueryData $query): ResolveSubscriptionContextResultData;
}
