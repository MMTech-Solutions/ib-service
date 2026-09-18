<?php

declare(strict_types=1);

namespace App\Features\Progression\Services;

use App\Features\Plans\Contracts\Ports\Input\ResolvePlanContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanProgressionContextPort;
use App\Features\Plans\Contracts\Ports\Input\ResolvePlanSubscriptionContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramSubscriptionContextPort;
use App\Features\Progression\Contracts\Ports\Output\FetchProgressionActivitiesPort;
use App\Features\Rules\Contracts\Ports\Input\ResolvePointsContributionContextPort;
use App\Features\Subscriptions\Contracts\Ports\Input\HasOpenSubscriptionsForPlanPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;

/**
 * Wiring of published inter-feature input ports consumed by Progression.
 */
final class ProgressionInterFeatureGateways
{
    public function __construct(
        private readonly FetchProgressionActivitiesPort $activities,
        private readonly ResolvePlanContextPort $planContext,
        private readonly ResolvePlanSubscriptionContextPort $planSubscriptionContext,
        private readonly ResolvePlanProgressionContextPort $planProgressionContext,
        private readonly ResolveProgramContextPort $programContext,
        private readonly ResolveProgramSubscriptionContextPort $programSubscriptionContext,
        private readonly HasOpenSubscriptionsForPlanPort $openSubscriptionsForPlan,
        private readonly ResolveSubscriptionContextPort $subscriptionContext,
        private readonly ResolvePointsContributionContextPort $pointsContributionContext,
    ) {}

    public function activities(): FetchProgressionActivitiesPort
    {
        return $this->activities;
    }

    public function planContext(): ResolvePlanContextPort
    {
        return $this->planContext;
    }

    public function planSubscriptionContext(): ResolvePlanSubscriptionContextPort
    {
        return $this->planSubscriptionContext;
    }

    public function planProgressionContext(): ResolvePlanProgressionContextPort
    {
        return $this->planProgressionContext;
    }

    public function programContext(): ResolveProgramContextPort
    {
        return $this->programContext;
    }

    public function programSubscriptionContext(): ResolveProgramSubscriptionContextPort
    {
        return $this->programSubscriptionContext;
    }

    public function openSubscriptionsForPlan(): HasOpenSubscriptionsForPlanPort
    {
        return $this->openSubscriptionsForPlan;
    }

    public function subscriptionContext(): ResolveSubscriptionContextPort
    {
        return $this->subscriptionContext;
    }

    public function pointsContributionContext(): ResolvePointsContributionContextPort
    {
        return $this->pointsContributionContext;
    }
}
