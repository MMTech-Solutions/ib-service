<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Commands;

use Spatie\LaravelData\Data;

final class ProgressionTemplateLevelCommandData extends Data
{
    public function __construct(
        public readonly int $distributionLevel,
        public readonly string $weight,
    ) {}
}
