<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class PlanListQueryData extends Data
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $search = null,
        public readonly ?bool $isActive = null,
    ) {}
}
