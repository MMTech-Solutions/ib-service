<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class ResolveNegativePnlRuleContextQueryData extends Data
{
    public function __construct(public readonly string $plan_id, public readonly string $program_id, public readonly string $module_id, public readonly string $rule_version_id, public readonly string $occurred_at) {}
}
