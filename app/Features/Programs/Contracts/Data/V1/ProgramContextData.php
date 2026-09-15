<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ProgramContextData extends Data
{
    /**
     * @param  list<string>  $selected_module_ids
     */
    public function __construct(
        public readonly string $id,
        public readonly string $plan_id,
        public readonly array $selected_module_ids,
    ) {}
}
