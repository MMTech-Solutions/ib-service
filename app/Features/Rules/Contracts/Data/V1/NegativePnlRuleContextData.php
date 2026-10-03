<?php

declare(strict_types=1);

namespace App\Features\Rules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlRuleContextData extends Data
{
    public function __construct(public readonly string $assignment_id, public readonly string $rule_id, public readonly string $rule_version_id, public readonly string $plan_payment_template_version_binding_id) {}
}
