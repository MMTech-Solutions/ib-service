<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class PlanModuleBindingData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $module_id,
        public readonly string $code,
        public readonly string $name,
        public readonly bool $is_active,
        public readonly string $processing_status,
        public readonly string $created_at,
    ) {}
}
