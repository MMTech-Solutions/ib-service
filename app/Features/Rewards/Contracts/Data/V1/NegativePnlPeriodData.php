<?php

declare(strict_types=1);

namespace App\Features\Rewards\Contracts\Data\V1;

use Spatie\LaravelData\Data;

final class NegativePnlPeriodData extends Data
{
    public function __construct(
        public readonly string $status,
        public readonly string $account_id,
        public readonly string $external_user_id,
        public readonly string $server_group_id,
        public readonly string $currency_code,
        public readonly int $currency_precision,
        public readonly ?string $balance_before,
        public readonly string $balance_after,
        public readonly ?string $occurred_from,
        public readonly string $occurred_until,
        public readonly string $deposits,
        public readonly string $withdrawals,
        public readonly string $cash_flow_net,
        public readonly ?string $net_pnl,
        public readonly NegativePnlCashFlowEvidenceData $evidence,
        public readonly ?string $balance_read_id = null,
        public readonly ?string $balance_read_at = null,
    ) {}

    public function establishesBaseline(): bool
    {
        return $this->status === 'baseline';
    }
}
