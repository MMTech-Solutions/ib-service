<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\DTOs;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use Spatie\LaravelData\Data;

final class VolumeRewardActivitiesPageData extends Data
{
    /** @param list<VolumeRewardActivityData> $activities */
    public function __construct(public readonly array $activities, public readonly ?string $next_cursor) {}
}
