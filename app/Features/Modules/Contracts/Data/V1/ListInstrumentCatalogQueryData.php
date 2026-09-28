<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ListInstrumentCatalogQueryData extends Data
{
    /** @param array<string, string> $filters */
    public function __construct(
        public readonly string $module_id,
        public readonly string $type,
        public readonly array $filters,
        public readonly int $page,
        public readonly int $per_page,
    ) {}
}
