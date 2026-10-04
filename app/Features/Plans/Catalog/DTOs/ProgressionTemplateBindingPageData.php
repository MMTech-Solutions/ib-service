<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionTemplateBindingPageData extends Data
{
    /** @param list<ProgressionTemplateBindingData> $items */
    public function __construct(public readonly array $items, public readonly int $total, public readonly int $perPage, public readonly int $currentPage) {}
}
