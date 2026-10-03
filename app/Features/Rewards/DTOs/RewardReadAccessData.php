<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class RewardReadAccessData extends Data
{
    public function __construct(public readonly bool $include_audit, public readonly ?string $beneficiary_id) {}
}
