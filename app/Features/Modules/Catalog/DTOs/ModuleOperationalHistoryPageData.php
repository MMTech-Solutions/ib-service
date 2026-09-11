<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ModuleOperationalHistoryPageData extends Data
{
    /**
     * @param  list<ModuleOperationalChangeData>  $entries
     */
    public function __construct(
        public readonly array $entries,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
