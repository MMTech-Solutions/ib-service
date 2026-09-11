<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Data;

use Spatie\LaravelData\Data;

final class ModuleCapabilityData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $is_active,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
