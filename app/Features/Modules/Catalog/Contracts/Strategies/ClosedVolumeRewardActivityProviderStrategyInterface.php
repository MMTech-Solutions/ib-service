<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Strategies;

use App\Features\Modules\Contracts\Data\V1\ResolveClosedVolumeRewardActivityQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;

interface ClosedVolumeRewardActivityProviderStrategyInterface
{
    public function fetch(ResolveClosedVolumeRewardActivityQueryData $query): VolumeRewardActivityData;
}
