<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaContributionData extends Data
{
    public function __construct(
        public readonly string $provider, public readonly string $source_activity_id,
        public readonly string $subject_external_user_id, public readonly string $kind,
        public readonly string $quantity, public readonly string $unit_code,
        public readonly string $points_per_unit, public readonly string $points,
        public readonly string $occurred_at, public readonly ?string $instrument_reference = null,
        public readonly ?int $amount_minor = null, public readonly ?string $currency_code = null,
    ) {}
}
