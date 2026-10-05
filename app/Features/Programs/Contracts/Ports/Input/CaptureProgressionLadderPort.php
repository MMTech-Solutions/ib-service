<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\CaptureProgressionLadderQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;

interface CaptureProgressionLadderPort
{
    public function execute(CaptureProgressionLadderQueryData $query): ProgressionLadderData;
}
