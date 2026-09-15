<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\DTOs;

use Spatie\LaravelData\Data;

final class RuleAssignmentsPageData extends Data
{
    /**
     * @param  list<RuleAssignmentData>  $assignments
     */
    public function __construct(
        public readonly array $assignments,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
