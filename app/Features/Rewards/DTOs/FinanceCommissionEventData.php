<?php

declare(strict_types=1);

namespace App\Features\Rewards\DTOs;

final readonly class FinanceCommissionEventData
{
    public function __construct(
        public int $id,
        public string $idempotency_key,
        public string $commission_type,
        public string $ib_user_id,
        public int $amount_minor,
        public int $minor_units,
        public string $reference_type,
        public string $reference_id,
        public string $status,
        public ?int $reverses_commission_event_id,
        public string $currency_code,
        public string $system_wallet_slug,
        public int $network_level,
    ) {}
}
