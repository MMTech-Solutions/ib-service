<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\AssertSelectedModuleQueryData;
use App\Features\Programs\Contracts\Data\V1\ProgramContextData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramContextQueryData;

interface ResolveProgramContextPort
{
    public function resolve(ResolveProgramContextQueryData $query): ProgramContextData;

    public function assertSelectedModule(AssertSelectedModuleQueryData $query): ProgramContextData;
}
