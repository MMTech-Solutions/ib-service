<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class VolumeRewardProgramConfigurationData extends Data
{
    public function __construct(
        public readonly ?string $program_volume_configuration_id,
        public readonly string $program_symbol_configuration_id,
        public readonly string $mode,
        public readonly string $plan_payment_template_version_binding_id,
        public readonly string $payment_template_version_id,
        public readonly string $commission_type,
        public readonly string $commission_value,
        public readonly int $distribution_level,
        public readonly string $template_level_rate,
        public readonly string $configured_currency_code,
    ) {}
}
