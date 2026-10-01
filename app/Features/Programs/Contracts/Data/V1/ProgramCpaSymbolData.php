<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ProgramCpaSymbolData extends Data
{
    public function __construct(
        public readonly string $symbol_reference,
        public readonly string $server_group_reference,
        public readonly string $currency_code,
    ) {}
}
