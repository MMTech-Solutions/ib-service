<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Ports\Input;

use App\Features\Rewards\Contracts\Data\V1\RecordNegativePnlClosureData;

interface RecordNegativePnlClosurePort
{
    public function execute(RecordNegativePnlClosureData $data): void;
}
