<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Data;

use Spatie\LaravelData\Data;

final class ModuleData extends Data
{
    /**
     * @param  list<ModuleCapabilityData>  $capabilities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $is_active,
        public readonly string $processing_status,
        public readonly array $capabilities,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
