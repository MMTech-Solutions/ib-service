<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Ports\Input;

use App\Features\Rules\Contracts\Data\V1\NegativePnlRuleContextData;
use App\Features\Rules\Contracts\Data\V1\ResolveNegativePnlRuleContextQueryData;

interface ResolveNegativePnlRuleContextPort
{
    public function execute(ResolveNegativePnlRuleContextQueryData $query): NegativePnlRuleContextData;
}
