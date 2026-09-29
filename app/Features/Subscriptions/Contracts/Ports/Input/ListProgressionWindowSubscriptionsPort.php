<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Ports\Input;

use App\Features\Subscriptions\Contracts\Data\V1\ListProgressionWindowSubscriptionsQueryData;
use App\Features\Subscriptions\Contracts\Data\V1\ProgressionWindowSubscriptionData;

interface ListProgressionWindowSubscriptionsPort
{
    /** @return list<ProgressionWindowSubscriptionData> */
    public function list(ListProgressionWindowSubscriptionsQueryData $query): array;
}
