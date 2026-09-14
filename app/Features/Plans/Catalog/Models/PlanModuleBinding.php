<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Models;

final class PlanModuleBinding
{
    public function __construct(
        public readonly string $id,
        public readonly string $planId,
        public readonly string $moduleId,
        public readonly string $createdAt,
    ) {}
}
