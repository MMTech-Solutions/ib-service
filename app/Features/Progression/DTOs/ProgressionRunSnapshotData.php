<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use App\Features\Programs\Contracts\Data\V1\ProgressionLadderData;
use Spatie\LaravelData\Data;

final class ProgressionRunSnapshotData extends Data
{
    /** @param list<array{subscription_id: string, is_evaluable: bool, contribution_ids: list<string>, total_points: string}> $participants */
    public function __construct(
        public readonly ProgressionLadderData $ladder,
        public readonly array $participants,
        public readonly string $captured_at,
        public readonly bool $legacy,
    ) {}
}
