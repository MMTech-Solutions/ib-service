<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlSubscriptionSegmentData extends Data
{
    public function __construct(public readonly string $program_id, public readonly string $starts_at, public readonly ?string $ends_at) {}
}
