<?php

declare(strict_types=1);

namespace App\Features\Rewards\Actions;

use App\Features\Rewards\DTOs\FinanceCommissionEventData;

final class ValidateRewardFinancialEventAction
{
    public function matches(object $reward, FinanceCommissionEventData $event, ?string $type = null, ?string $expectedKey = null): bool
    {
        $type ??= (string) $reward->commission_type;

        return $event->status === 'posted' && $event->commission_type === $type && $event->idempotency_key === ($expectedKey ?? $reward->settlement_idempotency_key)
            && $event->ib_user_id === $reward->beneficiary_user_id && $event->amount_minor === (int) $reward->amount_minor
            && $event->minor_units === (int) $reward->currency_precision && $event->reference_type === 'reward' && $event->reference_id === $reward->id
            && $event->currency_code === $reward->currency_code
            && $event->system_wallet_slug === strtolower($reward->currency_code).'-main'
            && $event->network_level === (int) $reward->network_level
            && ($type !== 'reversal' || $event->reverses_commission_event_id === (int) $reward->settlement_reference_id);
    }
}
