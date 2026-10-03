<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Ports\Input;

use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionData;

interface ListNegativePnlSubscriptionsPort
{
    /** @return list<NegativePnlSubscriptionData> */
    public function execute(string $programId, string $startsAt, ?string $endsAt, ?string $afterId, int $limit): array;
}
