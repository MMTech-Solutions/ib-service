<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class NegativePnlPeriodReadData extends Data
{
    /** @param array<string, mixed> $inputs @param array<string, mixed> $receipts @param array<string, mixed> $outcomes @param list<string> $reward_ids */
    public function __construct(public readonly string $id, public readonly string $job_id, public readonly string $status, public readonly string $occurred_until, public readonly array $inputs, public readonly array $receipts, public readonly array $outcomes, public readonly array $reward_ids) {}
}
