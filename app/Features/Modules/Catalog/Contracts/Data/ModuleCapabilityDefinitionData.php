<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Data;

use Spatie\LaravelData\Data;

final class ModuleCapabilityDefinitionData extends Data
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
    ) {}
}
