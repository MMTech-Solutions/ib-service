<?php

declare(strict_types=1);

namespace App\Features\Progression\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class FetchProgressionActivitiesQueryData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $occurred_from,
        public readonly string $occurred_until,
        public readonly ?string $cursor = null,
        public readonly ?int $limit = null,
    ) {}
}
