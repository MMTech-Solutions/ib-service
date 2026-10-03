<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Strategies;

use App\Features\Modules\Catalog\DTOs\VolumeRewardActivitiesPageData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;

interface VolumeRewardActivitiesProviderStrategyInterface
{
    public function fetch(ListVolumeRewardActivitiesQueryData $query): VolumeRewardActivitiesPageData;
}
