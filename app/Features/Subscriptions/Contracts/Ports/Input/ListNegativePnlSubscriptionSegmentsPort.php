<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Ports\Input;

use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionSegmentData;

interface ListNegativePnlSubscriptionSegmentsPort
{
    /** @return list<NegativePnlSubscriptionSegmentData> */
    public function execute(string $subscriptionId, string $from, string $until): array;
}
