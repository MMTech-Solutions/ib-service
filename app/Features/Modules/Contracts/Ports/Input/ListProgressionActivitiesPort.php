<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Ports\Input;

use App\Features\Modules\Contracts\Data\V1\ListProgressionActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\ListProgressionActivitiesResultData;

interface ListProgressionActivitiesPort
{
    public function list(ListProgressionActivitiesQueryData $query): ListProgressionActivitiesResultData;
}
