<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionReadPageData extends Data
{
    /** @param list<ProgressionDistributionReadData|ProgressionRunReadData|ProgressionResultReadData> $items */
    public function __construct(public readonly array $items, public readonly int $total) {}
}
