<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ActivityEvaluationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $module_id,
        public readonly string $source_activity_id,
        public readonly string $beneficiary_external_user_id,
        public readonly ?string $subscription_id,
        public readonly ?string $plan_id,
        public readonly ?string $program_id,
        public readonly string $occurred_at,
        public readonly ?string $window_starts_at,
        public readonly ?string $window_ends_at,
        public readonly string $metric_code,
        public readonly string $unit_code,
        public readonly ?string $instrument_reference,
        public readonly string $quantity,
        public readonly string $status,
        public readonly ?string $exclusion_reason,
        public readonly ?string $exclusion_explanation,
        public readonly string $evaluated_at,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly ?ContributionData $contribution,
    ) {}
}
