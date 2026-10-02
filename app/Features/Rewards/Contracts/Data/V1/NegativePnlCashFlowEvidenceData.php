<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlCashFlowEvidenceData extends Data
{
    /**
     * @param  list<string>  $external_deposit_references
     * @param  list<string>  $external_withdrawal_references
     * @param  list<string>  $internal_deposit_references
     * @param  list<string>  $internal_withdrawal_references
     */
    public function __construct(
        public readonly int $external_deposit_count,
        public readonly array $external_deposit_references,
        public readonly int $external_withdrawal_count,
        public readonly array $external_withdrawal_references,
        public readonly int $internal_deposit_count,
        public readonly array $internal_deposit_references,
        public readonly int $internal_withdrawal_count,
        public readonly array $internal_withdrawal_references,
    ) {}
}
