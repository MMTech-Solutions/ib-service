<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CaptureCpaContextResultData extends Data
{
    public function __construct(
        public readonly string $cpa_context_id,
        public readonly bool $created,
    ) {}
}
