<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolvePlanContextQueryData extends Data
{
    public function __construct(
        public readonly string $plan_id,
    ) {}
}
