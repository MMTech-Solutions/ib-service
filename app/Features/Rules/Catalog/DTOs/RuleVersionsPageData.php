<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class RuleVersionsPageData extends Data
{
    /**
     * @param  list<RuleVersionData>  $versions
     */
    public function __construct(
        public readonly array $versions,
    ) {}
}
