<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ActivityEvaluationsPageData extends Data
{
    /**
     * @param  list<ActivityEvaluationData>  $evaluations
     */
    public function __construct(
        public readonly array $evaluations,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
