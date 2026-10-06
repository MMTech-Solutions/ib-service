<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaVerificationProgressData extends Data
{
    /** @param list<CpaSourceProgressData> $sources */
    public function __construct(
        public readonly string $id, public readonly string $referred_user_id, public readonly string $status,
        public readonly string $observed_volume_points, public readonly string $required_volume_points,
        public readonly string $observed_deposit_points, public readonly string $required_deposit_points,
        public readonly int $observed_deposit_minor, public readonly string $deposit_currency_code, public readonly int $deposit_currency_precision,
        public readonly bool $volume_satisfied, public readonly bool $deposit_satisfied,
        public readonly string $observed_from, public readonly ?string $last_evaluated_at,
        public readonly array $sources, public readonly string $ib_user_id, public readonly string $plan_id,
        public readonly string $program_id, public readonly string $cpa_assignment_id,
        public readonly string $rule_id, public readonly string $rule_version_id,
        public readonly ?string $reward_id, public readonly ?string $last_error_code,
        public readonly ?string $reward_financial_status, public readonly ?string $reward_reconciliation_hold_code,
    ) {}
}
