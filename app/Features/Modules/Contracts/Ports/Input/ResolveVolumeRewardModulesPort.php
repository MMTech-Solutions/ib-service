<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;

interface ResolveVolumeRewardModulesPort
{
    /** @return list<ModuleSummaryData> */
    public function execute(?string $moduleId = null): array;
}
