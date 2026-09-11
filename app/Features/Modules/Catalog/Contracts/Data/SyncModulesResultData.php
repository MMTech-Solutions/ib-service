<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Contracts\Data;

use Spatie\LaravelData\Data;

final class SyncModulesResultData extends Data
{
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $deactivated = 0,
        public readonly int $capabilitiesActivated = 0,
        public readonly int $capabilitiesDeactivated = 0,
        public readonly int $pruned = 0,
        public readonly int $protected = 0,
    ) {}
}
