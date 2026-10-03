<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use Spatie\LaravelData\Data;

final class CpaRewardCalculationInputData extends Data
{
    public function __construct(
        public readonly string $required_volume,
        public readonly int $required_deposit_minor,
        public readonly CpaEvidenceData $evidence,
        public readonly string $initial_volume = '0',
        public readonly int $initial_deposit_minor = 0,
    ) {}
}
