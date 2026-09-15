<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class AssertSelectedModuleQueryData extends Data
{
    public function __construct(
        public readonly string $plan_id,
        public readonly string $program_id,
        public readonly string $module_id,
    ) {}
}
