<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ProgressionRunReadData extends Data
{
    public function __construct(public readonly string $id, public readonly string $plan_id, public readonly string $window_starts_at, public readonly string $window_ends_at, public readonly string $status, public readonly ?string $started_at, public readonly ?string $completed_at, public readonly ?ProgressionRunSnapshotData $snapshot) {}
}
