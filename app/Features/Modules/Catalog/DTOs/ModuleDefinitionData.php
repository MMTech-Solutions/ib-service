<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ModuleDefinitionData extends Data
{
    /**
     * @param  list<ModuleCapabilityDefinitionData>  $capabilities
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $capabilities,
    ) {}
}
