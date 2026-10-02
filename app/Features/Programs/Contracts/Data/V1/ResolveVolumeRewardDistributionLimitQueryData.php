<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveVolumeRewardDistributionLimitQueryData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $occurred_from,
        public readonly string $occurred_until,
        public readonly string $channel,
    ) {}
}
