<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ListVolumeRewardActivitiesResultData extends Data
{
    /** @param list<VolumeRewardActivityData> $activities */
    public function __construct(
        public readonly string $module_id,
        public readonly string $module_condition,
        public readonly bool $provider_invoked,
        public readonly array $activities,
        public readonly ?string $next_cursor,
        public readonly ?string $rejection_code,
    ) {}
}
