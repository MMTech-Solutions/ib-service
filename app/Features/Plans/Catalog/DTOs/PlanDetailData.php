<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class PlanDetailData extends Data
{
    /**
     * @param  list<PlanModuleBindingData>  $modules
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $is_active,
        public readonly int $lock_version,
        public readonly array $modules,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
