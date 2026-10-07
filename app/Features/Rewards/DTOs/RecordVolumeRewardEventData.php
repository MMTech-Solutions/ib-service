<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use Spatie\LaravelData\Data;

final class RecordVolumeRewardEventData extends Data
{
    /** @param array<string, int|string|null> $transport_snapshot */
    public function __construct(public readonly string $event_id, public readonly int $schema_version, public readonly VolumeRewardActivityData $activity, public readonly array $transport_snapshot) {}
}
