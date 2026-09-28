<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Http\V1\Commands;

use Spatie\LaravelData\Data;

final class PaymentTemplateLevelCommandData extends Data
{
    public function __construct(
        public readonly int $distributionLevel,
        public readonly string $rate,
    ) {}
}
