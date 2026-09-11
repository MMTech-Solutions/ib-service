<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use Spatie\LaravelData\Data;

final class ModuleOperationalChangeData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $module_id,
        public readonly string $action,
        public readonly string $actor_iam_id,
        public readonly string $reason,
        public readonly bool $previous_is_active,
        public readonly string $previous_processing_status,
        public readonly bool $next_is_active,
        public readonly string $next_processing_status,
        public readonly string $occurred_at,
    ) {}
}
