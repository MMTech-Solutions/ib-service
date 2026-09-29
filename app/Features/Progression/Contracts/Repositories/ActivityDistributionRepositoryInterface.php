<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Repositories;

use App\Features\Progression\Models\ActivityDistribution;
use Closure;

interface ActivityDistributionRepositoryInterface
{
    public function transaction(Closure $callback): mixed;

    public function findBySourceActivity(string $moduleId, string $sourceActivityId): ?ActivityDistribution;

    public function record(ActivityDistribution $distribution): ActivityDistribution;
}
