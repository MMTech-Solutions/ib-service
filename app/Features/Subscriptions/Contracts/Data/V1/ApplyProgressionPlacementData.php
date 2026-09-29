<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ApplyProgressionPlacementData extends Data
{
    public function __construct(public string $subscription_id, public string $target_program_id, public string $operation_id, public string $occurred_at) {}
}
