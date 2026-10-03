<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class NegativePnlJobReadData extends Data
{
    /** @param array<string, mixed>|null $closure */
    public function __construct(public readonly string $id, public readonly string $subscription_id, public readonly string $beneficiary_id, public readonly string $plan_id, public readonly string $module_id, public readonly string $server_group_id, public readonly string $cadence, public readonly string $status, public readonly string $next_cut_at, public readonly ?string $cursor_at, public readonly ?string $closed_at, public readonly ?string $finished_at, public readonly ?string $error_code, public readonly ?string $retry_at, public readonly ?array $closure) {}
}
