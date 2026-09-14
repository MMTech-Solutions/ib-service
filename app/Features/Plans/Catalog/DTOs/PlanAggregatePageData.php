<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use App\Features\Plans\Catalog\Models\Plan;

final class PlanAggregatePageData
{
    /**
     * @param  list<Plan>  $plans
     */
    public function __construct(
        public readonly array $plans,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
