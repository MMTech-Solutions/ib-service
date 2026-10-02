<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class CpaVerificationProgressData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $referred_user_id,
        public readonly string $status,
        public readonly string $observed_volume,
        public readonly string $required_volume,
        public readonly string $volume_unit_code,
        public readonly int $observed_deposit_minor,
        public readonly int $required_deposit_minor,
        public readonly string $currency_code,
        public readonly int $currency_precision,
        public readonly bool $volume_satisfied,
        public readonly bool $deposit_satisfied,
        public readonly string $observed_from,
        public readonly ?string $observed_until,
        public readonly ?string $last_evaluated_at,
        public readonly ?string $ib_user_id,
        public readonly ?string $plan_id,
        public readonly ?string $program_id,
        public readonly ?string $module_id,
        public readonly ?string $rule_assignment_id,
        public readonly ?string $rule_id,
        public readonly ?string $rule_version_id,
        public readonly ?string $reward_id,
        public readonly ?string $last_error_code,
        public readonly ?string $reward_financial_status,
        public readonly ?string $reward_reconciliation_hold_code,
    ) {}
}
