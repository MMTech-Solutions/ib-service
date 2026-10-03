<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Programs\Contracts\Data\V1\NegativePnlGroupConfigurationData;
use App\Features\Rewards\Contracts\Data\V1\NegativePnlReferralData;
use App\Features\Subscriptions\Contracts\Data\V1\SubscriptionContextData;
use Spatie\LaravelData\Data;

final class NegativePnlFrozenInputsData extends Data
{
    /** @param list<NegativePnlReferralData> $referrals */
    public function __construct(public readonly SubscriptionContextData $subscription, public readonly NegativePnlGroupConfigurationData $configuration, public readonly string $configuration_id, public readonly string $minimum_amount_major, public readonly array $referrals, public readonly bool $reset_baseline = false) {}
}
