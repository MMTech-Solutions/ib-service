<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ModuleListQueryData extends Data
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $search = null,
        public readonly ?bool $isActive = null,
        public readonly ?string $processingStatus = null,
    ) {}
}
