<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class CertifiedDepositEvidenceData extends Data
{
    public function __construct(public readonly string $source_activity_id, public readonly string $subject_external_user_id, public readonly int $amount_minor, public readonly string $currency_code, public readonly string $occurred_at, public readonly string $provider = 'finance') {}
}
