<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class VolumeRewardCalculationData extends Data
{
    public function __construct(
        public readonly string $commission_type,
        public readonly string $quantity,
        public readonly string $broker_granted_commission,
        public readonly string $participation_rate,
        public readonly string $template_level_rate,
        public readonly string $personal_rate,
        public readonly bool $is_master,
        public readonly string $master_rate,
        public readonly string $currency_code,
        public readonly int $currency_precision,
        public readonly string $minimum_amount_major,
    ) {}
}
