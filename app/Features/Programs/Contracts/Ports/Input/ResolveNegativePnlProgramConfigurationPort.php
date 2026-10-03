<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;

interface ResolveNegativePnlProgramConfigurationPort
{
    public function execute(ResolveNegativePnlProgramConfigurationQueryData $query): ?NegativePnlProgramConfigurationData;
}
