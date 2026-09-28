<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class InstrumentCatalogItemData extends Data
{
    /** @param array<string, string> $parents */
    public function __construct(
        public readonly string $reference,
        public readonly string $type,
        public readonly string $name,
        public readonly array $parents,
        public readonly ?string $currency_code = null,
    ) {}
}
