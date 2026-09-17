<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NormalizedActivityData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $source_activity_id,
        public readonly string $subject_external_user_id,
        public readonly string $metric_code,
        public readonly string $unit_code,
        public readonly string $quantity,
        public readonly string $occurred_at,
        public readonly ?string $instrument_reference = null,
    ) {}
}
