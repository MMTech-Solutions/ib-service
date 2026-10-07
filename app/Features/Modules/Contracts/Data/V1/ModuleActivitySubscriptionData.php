<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ModuleActivitySubscriptionData extends Data
{
    public function __construct(
        public readonly string $module_code,
        public readonly string $topic,
        public readonly string $event_name,
        public readonly int $schema_version,
        public readonly string $adapter,
    ) {}
}
