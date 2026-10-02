<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\ResolveClosedVolumeRewardActivityQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;

interface ResolveClosedVolumeRewardActivityPort
{
    public function execute(ResolveClosedVolumeRewardActivityQueryData $query): VolumeRewardActivityData;
}
