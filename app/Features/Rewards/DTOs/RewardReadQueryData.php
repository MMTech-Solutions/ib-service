<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class RewardReadQueryData extends Data
{
    /** @param array<string, string> $filters */
    public function __construct(public readonly string $resource, public readonly ?string $id, public readonly ?string $beneficiary_id, public readonly bool $include_audit, public readonly array $filters, public readonly int $page = 1, public readonly int $per_page = 25) {}
}
