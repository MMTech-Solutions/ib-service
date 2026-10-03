<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;

interface ListNegativePnlConfigurationsPort
{
    /** @return list<NegativePnlProgramConfigurationData> */
    public function execute(?string $afterId, int $limit, ?string $programId = null, ?string $from = null, ?string $until = null): array;
}
