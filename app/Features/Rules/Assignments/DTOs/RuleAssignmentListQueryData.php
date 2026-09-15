<?php

declare(strict_types=1);

namespace App\Features\Rules\Assignments\DTOs;

use Spatie\LaravelData\Data;

final class RuleAssignmentListQueryData extends Data
{
    public function __construct(
        public readonly string $ruleId,
        public readonly int $page = 1,
        public readonly int $perPage = 100,
        public readonly ?string $programId = null,
        public readonly ?string $moduleId = null,
        public readonly ?bool $active = null,
    ) {}
}
