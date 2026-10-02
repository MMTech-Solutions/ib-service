<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaProgressReadAccessData extends Data
{
    public function __construct(
        public readonly bool $is_administrative,
        public readonly ?string $forced_ib_user_id,
    ) {}
}
