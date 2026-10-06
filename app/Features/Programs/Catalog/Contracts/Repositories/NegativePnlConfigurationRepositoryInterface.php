<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Contracts\Repositories;

use App\Features\Programs\Contracts\Data\V1\NegativePnlModuleConfigurationData;
use App\Features\Programs\Contracts\Data\V1\NegativePnlProgramConfigurationData;
use App\Features\Programs\Contracts\Data\V1\ResolveNegativePnlProgramConfigurationQueryData;
use Closure;

interface NegativePnlConfigurationRepositoryInterface
{
    public function transactionForProgram(string $programId, Closure $callback): mixed;

    /** @param list<NegativePnlModuleConfigurationData> $modules */
    public function replace(string $programId, string $cadence, string $actorId, string $at, array $modules): ?NegativePnlProgramConfigurationData;

    public function resolve(ResolveNegativePnlProgramConfigurationQueryData $query): ?NegativePnlProgramConfigurationData;

    /** @return list<NegativePnlProgramConfigurationData> */
    public function list(?string $afterId, int $limit, ?string $programId = null, ?string $from = null, ?string $until = null): array;
}
