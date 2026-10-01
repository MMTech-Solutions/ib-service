<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Ports\Input;

use App\Features\Programs\Contracts\Data\V1\ProgramCpaSymbolData;
use App\Features\Programs\Contracts\Data\V1\ResolveProgramCpaSymbolsQueryData;

interface ResolveProgramCpaSymbolsPort
{
    /** @return list<ProgramCpaSymbolData> */
    public function resolve(ResolveProgramCpaSymbolsQueryData $query): array;
}
