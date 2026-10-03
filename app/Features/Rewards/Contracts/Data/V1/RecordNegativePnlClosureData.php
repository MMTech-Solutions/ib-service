<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class RecordNegativePnlClosureData extends Data
{
    public function __construct(public readonly string $subscription_id, public readonly string $incoming_subscription_id, public readonly string $operation_id, public readonly string $closed_at) {}
}
