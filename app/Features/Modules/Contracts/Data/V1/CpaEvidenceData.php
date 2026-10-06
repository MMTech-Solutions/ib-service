<?php

declare(strict_types=1);

namespace App\Features\Modules\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class CpaEvidenceData extends Data
{
    /** @param list<CpaVolumeEvidenceData> $volume_facts @param list<CertifiedDepositEvidenceData> $deposit_facts */
    public function __construct(
        public readonly array $volume_facts,
        public readonly array $deposit_facts,
    ) {}
}
