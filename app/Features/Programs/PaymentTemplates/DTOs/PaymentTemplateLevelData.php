<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\DTOs;

use Spatie\LaravelData\Data;

final class PaymentTemplateLevelData extends Data
{
    public function __construct(
        public readonly int $distribution_level,
        public readonly string $rate,
    ) {}
}
