<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Catalog\Actions;

use App\Features\Plans\Contracts\Data\V1\PlanSubscriptionContextData;
use App\Features\Subscriptions\Catalog\Exceptions\PlanNotEligibleForSubscriptionException;

final class AssertPlanEligibleForSubscriptionAction
{
    public function assert(PlanSubscriptionContextData $plan): void
    {
        if (! $plan->is_active || $plan->archived) {
            throw PlanNotEligibleForSubscriptionException::forPlan($plan->id);
        }
    }
}
