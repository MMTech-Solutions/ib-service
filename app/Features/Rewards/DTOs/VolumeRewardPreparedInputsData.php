<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class VolumeRewardPreparedInputsData extends Data
{
    /** @param list<PersistVolumeRewardData> $rewards @param array<string, string> $outcomes */
    public function __construct(public readonly array $rewards, public readonly int $skipped, public readonly array $outcomes = []) {}
}
