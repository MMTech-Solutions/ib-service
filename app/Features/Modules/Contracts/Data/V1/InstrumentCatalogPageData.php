<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class InstrumentCatalogPageData extends Data
{
    /** @param list<InstrumentCatalogItemData> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $per_page,
    ) {}
}
