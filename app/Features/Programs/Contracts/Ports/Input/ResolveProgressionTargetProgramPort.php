<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\ProgressionTargetProgramData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgressionTargetProgramQueryData;

interface ResolveProgressionTargetProgramPort
{
    public function resolve(ResolveProgressionTargetProgramQueryData $query): ProgressionTargetProgramData;
}
