<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class VolumeRewardEventData extends Data
{
    public function __construct(public readonly string $event_id, public readonly int $schema_version, public readonly VolumeRewardActivityData $activity) {}
}
