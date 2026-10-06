<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use Spatie\LaravelData\Data;

final class CpaRewardCalculationInputData extends Data
{
    /** @param array<string, mixed> $configuration */
    public function __construct(
        public readonly array $configuration, public readonly CpaEvidenceData $evidence,
        public readonly string $points_per_volume_unit = '0',
        public readonly string $initial_volume_points = '0',
        public readonly string $initial_deposit_points = '0',
    ) {}
}
