<?php

declare(strict_types=1);

namespace App\Features\Scheduling\DTOs;

use Spatie\LaravelData\Data;

final class ManualRunData extends Data
{
    public function __construct(public readonly string $code, public readonly string $actor_id, public readonly string $reason, public readonly string $idempotency_key) {}
}
