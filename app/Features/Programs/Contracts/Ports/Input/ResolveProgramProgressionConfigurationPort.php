<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\ProgramProgressionConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramProgressionConfigurationQueryData;

interface ResolveProgramProgressionConfigurationPort
{
    public function resolve(ResolveProgramProgressionConfigurationQueryData $query): ?ProgramProgressionConfigurationData;
}
