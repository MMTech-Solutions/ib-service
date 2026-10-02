<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveClosedVolumeRewardActivityQueryData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $order_id,
        public readonly string $external_trader_id,
    ) {}
}
