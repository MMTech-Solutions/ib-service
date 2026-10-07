<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardEventData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardEventQueryData;

interface NormalizeVolumeRewardEventPort
{
    public function execute(VolumeRewardEventQueryData $query): VolumeRewardEventData;
}
