<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ModuleOperationalHistoryQueryData extends Data
{
    public function __construct(
        public readonly string $moduleId,
        public readonly int $page = 1,
        public readonly int $perPage = 100,
    ) {}
}
