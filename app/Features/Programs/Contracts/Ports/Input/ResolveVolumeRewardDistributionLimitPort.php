<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\ResolveVolumeRewardDistributionLimitQueryData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardDistributionLimitData;

interface ResolveVolumeRewardDistributionLimitPort
{
    public function execute(ResolveVolumeRewardDistributionLimitQueryData $query): ?VolumeRewardDistributionLimitData;
}
