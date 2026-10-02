<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class VolumeRewardDistributionLimitData extends Data
{
    /** @param list<string> $instrument_references */
    public function __construct(
        public readonly array $instrument_references,
        public readonly int $max_distribution_level,
    ) {}
}
