<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ProgramProgressionConfigurationData extends Data
{
    public function __construct(
        public readonly string $program_symbol_configuration_id,
        public readonly string $plan_progression_template_version_binding_id,
        public readonly string $progression_template_version_id,
        public readonly string $distribution_weight,
    ) {}
}
