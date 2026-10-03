<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaRewardCalculationResultData extends Data
{
    public function __construct(
        public readonly string $volume,
        public readonly int $deposit_minor,
        public readonly bool $qualified,
    ) {}
}
