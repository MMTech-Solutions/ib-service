<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveProgramCpaSymbolsQueryData extends Data
{
    public function __construct(
        public readonly string $program_id,
        public readonly string $module_id,
        public readonly string $occurred_at,
    ) {}
}
