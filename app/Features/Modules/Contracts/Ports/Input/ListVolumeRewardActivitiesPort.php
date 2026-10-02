<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesResultData;

interface ListVolumeRewardActivitiesPort
{
    public function execute(ListVolumeRewardActivitiesQueryData $query): ListVolumeRewardActivitiesResultData;
}
