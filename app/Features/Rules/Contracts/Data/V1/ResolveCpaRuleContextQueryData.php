<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveCpaRuleContextQueryData extends Data
{
    public function __construct(
        public readonly string $program_id,
        public readonly string $occurred_at,
    ) {}
}
