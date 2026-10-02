<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class VolumeRewardCalculationData
{
    public function __construct(
        public string $commission_type,
        public string $quantity,
        public string $broker_granted_commission,
        public string $participation_rate,
        public string $template_level_rate,
        public string $personal_rate,
        public bool $is_master,
        public string $master_rate,
        public string $currency_code,
        public int $currency_precision,
    ) {}
}
