<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ListProgressionActivitiesQueryData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $occurred_from,
        public readonly string $occurred_until,
        public readonly ?string $cursor = null,
        public readonly ?int $limit = null,
    ) {}
}
