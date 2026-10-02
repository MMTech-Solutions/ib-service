<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Ports\Input;

use App\Features\Rules\Contracts\Data\V1\ResolveVolumeRewardRuleContextQueryData;
use App\Features\Rules\Contracts\Data\V1\VolumeRewardRuleContextData;

interface ResolveVolumeRewardRuleContextPort
{
    public function execute(ResolveVolumeRewardRuleContextQueryData $query): VolumeRewardRuleContextData;
}
