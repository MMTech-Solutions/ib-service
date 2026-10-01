<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Input;

use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CaptureCpaContextResultData;

interface CaptureCpaContextPort
{
    public function execute(CaptureCpaContextData $data): CaptureCpaContextResultData;
}
