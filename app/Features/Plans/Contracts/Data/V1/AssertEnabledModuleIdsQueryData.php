<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class AssertEnabledModuleIdsQueryData extends Data
{
    /**
     * @param  list<string>  $module_ids
     */
    public function __construct(
        public readonly string $plan_id,
        public readonly array $module_ids = [],
    ) {}
}
