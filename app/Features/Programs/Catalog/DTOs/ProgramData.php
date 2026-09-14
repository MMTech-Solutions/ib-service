<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ProgramData extends Data
{
    /**
     * @param  list<string>  $module_ids
     */
    public function __construct(
        public readonly string $id,
        public readonly string $plan_id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $position,
        public readonly int $lock_version,
        public readonly array $module_ids,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
