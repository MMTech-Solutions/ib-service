<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardProgramConfigurationQueryData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardProgramConfigurationData;

interface ResolveVolumeRewardProgramConfigurationPort
{
    public function execute(ResolveVolumeRewardProgramConfigurationQueryData $query): ?VolumeRewardProgramConfigurationData;
}
