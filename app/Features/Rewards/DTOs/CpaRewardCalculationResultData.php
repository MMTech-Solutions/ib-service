<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaRewardCalculationResultData extends Data
{
    /** @param list<CpaContributionData> $contributions */
    public function __construct(public readonly string $volume_points, public readonly string $deposit_points, public readonly bool $qualified, public readonly array $contributions) {}
}
