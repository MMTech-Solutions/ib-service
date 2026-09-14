<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class PlanContextData extends Data
{
    /**
     * @param  list<string>  $enabled_module_ids
     */
    public function __construct(
        public readonly string $id,
        public readonly bool $archived,
        public readonly array $enabled_module_ids,
    ) {}
}
