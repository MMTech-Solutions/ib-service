<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Output;

use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineQueryData;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;

interface ResolveRewardUplinePort
{
    public function resolve(ResolveRewardUplineQueryData $query): ResolveRewardUplineResultData;
}
