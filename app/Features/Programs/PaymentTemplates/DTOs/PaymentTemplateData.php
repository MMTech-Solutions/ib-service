<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\DTOs;

use Spatie\LaravelData\Data;

final class PaymentTemplateData extends Data
{
    /**
     * @param  list<PaymentTemplateVersionData>  $versions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $lock_version,
        public readonly array $versions,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
