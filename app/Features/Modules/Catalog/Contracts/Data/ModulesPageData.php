<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Data;

use Spatie\LaravelData\Data;

final class ModulesPageData extends Data
{
    /**
     * @param  list<ModuleData>  $modules
     */
    public function __construct(
        public readonly array $modules,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
}
