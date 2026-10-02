<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaVerificationProgressListQueryData extends Data
{
    public function __construct(
        public readonly int $page,
        public readonly int $per_page,
        public readonly ?string $ib_user_id,
        public readonly ?string $referred_user_id,
        public readonly ?string $program_id,
        public readonly ?string $module_id,
        public readonly ?string $status,
    ) {}
}
