<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\UseCases;

use App\Features\Subscriptions\Catalog\Actions\ResolveSubscriptionContextMatchesAction;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextResultData;
use App\Features\Subscriptions\Contracts\Exceptions\AmbiguousSubscriptionContextException;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;

final class ResolveSubscriptionContextUseCase implements ResolveSubscriptionContextPort
{
    public function __construct(
        private readonly ResolveSubscriptionContextMatchesAction $matches,
    ) {}

    public function resolve(ResolveSubscriptionContextQueryData $query): ResolveSubscriptionContextResultData
    {
        $matches = $this->matches->matchesAt(
            externalUserId: $query->external_user_id,
            occurredAt: $query->occurred_at,
        );

        $count = count($matches);
        if ($count === 0) {
            return ResolveSubscriptionContextResultData::absent();
        }

        if ($count > 1) {
            throw AmbiguousSubscriptionContextException::forUserAt(
                $query->external_user_id,
                $query->occurred_at,
            );
        }

        return ResolveSubscriptionContextResultData::foundContext($matches[0]);
    }
}
