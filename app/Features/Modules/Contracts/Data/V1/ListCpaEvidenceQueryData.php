<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ListCpaEvidenceQueryData extends Data
{
    /** @param list<array{symbol_reference: string, server_group_reference: string, currency_code: string}> $instruments */
    public function __construct(
        public readonly string $module_id,
        public readonly string $subject_external_user_id,
        public readonly string $occurred_from,
        public readonly string $occurred_until,
        public readonly string $currency_code,
        public readonly int $currency_precision,
        public readonly array $instruments,
    ) {}
}
