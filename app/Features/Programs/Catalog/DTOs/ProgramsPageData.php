<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ProgramsPageData extends Data
{
    /**
     * @param  list<ProgramData>  $programs
     */
    public function __construct(
        public readonly array $programs,
    ) {}
}
