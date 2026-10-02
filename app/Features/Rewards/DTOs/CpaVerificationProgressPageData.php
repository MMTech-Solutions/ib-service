<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaVerificationProgressPageData extends Data
{
    /** @param list<CpaVerificationProgressData> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $current_page,
        public readonly int $per_page,
        public readonly int $total,
        public readonly int $last_page,
    ) {}
}
