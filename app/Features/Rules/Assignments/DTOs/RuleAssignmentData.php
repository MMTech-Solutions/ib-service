<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\DTOs;

use Spatie\LaravelData\Data;

final class RuleAssignmentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $rule_id,
        public readonly string $rule_version_id,
        public readonly string $program_id,
        public readonly string $module_id,
        public readonly string $scope_type,
        public readonly string $starts_at,
        public readonly ?string $ends_at,
        public readonly bool $active,
        public readonly int $lock_version,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
