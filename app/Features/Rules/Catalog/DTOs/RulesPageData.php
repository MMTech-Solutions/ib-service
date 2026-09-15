<?php

declare(strict_types=1);

namespace App\Features\Rules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class RulesPageData extends Data
{
    /**
     * @param  list<RuleData>  $rules
     */
    public function __construct(
        public readonly array $rules,
    ) {}
}
