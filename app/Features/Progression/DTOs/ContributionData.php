<?php

declare(strict_types=1);

namespace App\Features\Progression\DTOs;

use Spatie\LaravelData\Data;

final class ContributionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $evaluation_id,
        public readonly string $rule_id,
        public readonly string $rule_version_id,
        public readonly string $rule_assignment_id,
        public readonly string $strategy_type,
        public readonly string $scope_type,
        public readonly string $weight,
        public readonly string $points,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
