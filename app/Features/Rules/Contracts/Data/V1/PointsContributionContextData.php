<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class PointsContributionContextData extends Data
{
    public function __construct(
        public readonly string $rule_id,
        public readonly string $rule_version_id,
        public readonly string $rule_assignment_id,
        public readonly string $strategy_type,
        public readonly string $scope_type,
        public readonly string $unit,
        public readonly string $weight,
    ) {}
}
