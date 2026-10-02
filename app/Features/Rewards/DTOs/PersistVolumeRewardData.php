<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

use Spatie\LaravelData\Data;

final class PersistVolumeRewardData extends Data
{
    /** @param array<string, mixed> $summary_snapshot */
    public function __construct(
        public readonly string $beneficiary_user_id,
        public readonly string $plan_id,
        public readonly string $program_id,
        public readonly string $module_id,
        public readonly string $rule_assignment_id,
        public readonly string $rule_id,
        public readonly string $rule_version_id,
        public readonly int $amount_minor,
        public readonly string $currency_code,
        public readonly int $currency_precision,
        public readonly int $network_level,
        public readonly string $origin_idempotency_key,
        public readonly array $summary_snapshot,
        public readonly string $source_activity_id,
        public readonly string $subject_external_user_id,
        public readonly string $quantity,
        public readonly string $unit_code,
        public readonly string $occurred_at,
        public readonly string $instrument_reference,
    ) {}
}
