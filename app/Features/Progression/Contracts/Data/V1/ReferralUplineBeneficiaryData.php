<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ReferralUplineBeneficiaryData extends Data
{
    public function __construct(
        public readonly string $beneficiary_external_user_id,
        public readonly int $distribution_level,
    ) {}
}
