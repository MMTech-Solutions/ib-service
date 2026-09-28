<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionTemplateLevelData extends Data
{
    public function __construct(
        public readonly int $distribution_level,
        public readonly string $weight,
    ) {}
}
