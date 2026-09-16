<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveFirstProgramByPositionQueryData extends Data
{
    public function __construct(
        public readonly string $plan_id,
    ) {}
}
