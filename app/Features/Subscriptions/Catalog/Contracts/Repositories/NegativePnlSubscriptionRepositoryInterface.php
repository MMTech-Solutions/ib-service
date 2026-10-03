<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Contracts\Repositories;

use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionData;
use App\Features\Subscriptions\Contracts\Data\V1\NegativePnlSubscriptionSegmentData;

interface NegativePnlSubscriptionRepositoryInterface
{
    /** @return list<NegativePnlSubscriptionData> */
    public function list(string $programId, string $startsAt, ?string $endsAt, ?string $afterId, int $limit): array;

    /** @return list<NegativePnlSubscriptionSegmentData> */
    public function segments(string $subscriptionId, string $from, string $until): array;
}
