<?php

declare(strict_types=1);

namespace App\Features\Programs\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlModuleConfigurationData extends Data
{
    /** @param list<NegativePnlPaymentLevelData> $levels */
    public function __construct(public readonly string $module_id, public readonly string $assignment_id, public readonly string $rule_id, public readonly string $rule_version_id, public readonly string $plan_payment_template_version_binding_id, public readonly string $payment_template_version_id, public readonly array $levels) {}
}
