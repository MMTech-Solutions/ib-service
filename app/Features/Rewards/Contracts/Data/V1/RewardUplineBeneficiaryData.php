<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class RewardUplineBeneficiaryData extends Data
{
    public function __construct(
        public readonly string $beneficiary_external_user_id,
        public readonly int $distribution_level,
    ) {}
}
