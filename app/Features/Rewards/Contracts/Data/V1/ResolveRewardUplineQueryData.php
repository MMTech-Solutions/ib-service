<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveRewardUplineQueryData extends Data
{
    public function __construct(
        public readonly string $subject_external_user_id,
        public readonly int $max_distribution_level,
    ) {}
}
