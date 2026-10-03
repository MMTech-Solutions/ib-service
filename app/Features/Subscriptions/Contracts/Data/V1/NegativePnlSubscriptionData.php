<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlSubscriptionData extends Data
{
    public function __construct(public readonly string $id, public readonly string $external_user_id, public readonly string $plan_id, public readonly string $activated_at, public readonly ?string $closed_at, public readonly ?string $incoming_subscription_id, public readonly ?string $operation_id, public readonly ?string $relevant_from = null) {}
}
