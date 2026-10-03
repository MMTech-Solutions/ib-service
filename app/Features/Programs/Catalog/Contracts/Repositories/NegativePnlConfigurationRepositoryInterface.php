<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Contracts\Repositories;

use App\Features\Programs\Contracts\Data\V1\NegativePnlGroupConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;
use Closure;

interface NegativePnlConfigurationRepositoryInterface
{
    public function transactionForProgram(string $programId, Closure $callback): mixed;

    /** @param list<NegativePnlGroupConfigurationData> $groups */
    public function replace(string $programId, string $cadence, string $actorId, string $at, array $groups): ?NegativePnlProgramConfigurationData;

    public function resolve(ResolveNegativePnlProgramConfigurationQueryData $query): ?NegativePnlProgramConfigurationData;
}
