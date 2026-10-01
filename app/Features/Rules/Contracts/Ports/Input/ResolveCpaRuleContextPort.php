<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Ports\Input;

use App\Features\Rules\Contracts\Data\V1\CpaRuleContextData;
use App\Features\Rules\Contracts\Data\V1\ResolveCpaRuleContextQueryData;

interface ResolveCpaRuleContextPort
{
    public function resolve(ResolveCpaRuleContextQueryData $query): CpaRuleContextData;
}
