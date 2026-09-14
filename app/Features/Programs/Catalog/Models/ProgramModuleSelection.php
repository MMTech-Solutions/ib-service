<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Models;

final class ProgramModuleSelection
{
    public function __construct(
        public readonly string $id,
        public readonly string $programId,
        public readonly string $moduleId,
        public readonly string $createdAt,
    ) {}
}
