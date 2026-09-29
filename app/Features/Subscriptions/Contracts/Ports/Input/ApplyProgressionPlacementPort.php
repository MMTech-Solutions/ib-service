<?php

declare(strict_types=1);

namespace App\Features\Subscriptions\Contracts\Ports\Input;

use App\Features\Subscriptions\Contracts\Data\V1\ApplyProgressionPlacementData;
use App\Features\Subscriptions\Contracts\Enums\ProgressionPlacementOutcome;

interface ApplyProgressionPlacementPort
{
    public function apply(ApplyProgressionPlacementData $data): ProgressionPlacementOutcome;
}
