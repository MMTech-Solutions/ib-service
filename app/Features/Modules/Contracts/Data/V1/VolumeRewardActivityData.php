<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class VolumeRewardActivityData extends Data
{
    public function __construct(
        public readonly string $module_id,
        public readonly string $source_activity_id,
        public readonly string $subject_external_user_id,
        public readonly string $unit_code,
        public readonly string $quantity,
        public readonly string $occurred_at,
        public readonly string $instrument_reference,
        public readonly ?string $currency_code,
        public readonly ?int $currency_precision,
        public readonly ?string $broker_granted_commission,
    ) {}
}
