<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class PlansPageData extends Data
{
    /**
     * @param  list<PlanData>  $plans
     */
    public function __construct(
        public readonly array $plans,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
