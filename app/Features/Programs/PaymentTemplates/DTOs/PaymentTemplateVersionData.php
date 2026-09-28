<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\DTOs;

use Spatie\LaravelData\Data;

final class PaymentTemplateVersionData extends Data
{
    /**
     * @param  list<PaymentTemplateLevelData>  $levels
     */
    public function __construct(
        public readonly string $id,
        public readonly int $version_number,
        public readonly string $status,
        public readonly ?string $published_at,
        public readonly int $lock_version,
        public readonly array $levels,
    ) {}
}
