<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CaptureCpaContextData extends Data
{
    public function __construct(
        public readonly string $referred_user_id,
        public readonly string $ib_user_id,
        public readonly string $captured_at,
    ) {}
}
