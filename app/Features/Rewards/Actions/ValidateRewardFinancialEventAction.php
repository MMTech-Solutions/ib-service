<?php

declare(strict_types=1);

namespace App\Features\Rewards\Actions;

use App\Features\Rewards\DTOs\FinanceCommissionEventData;
use App\Features\Rewards\DTOs\RewardSettlementRequestData;

final class ValidateRewardFinancialEventAction
{
    public function __construct(private readonly BuildRewardFinancialRequestAction $requests) {}

    public function matches(object $reward, FinanceCommissionEventData $event, ?string $type = null, ?string $expectedKey = null): bool
    {
        $type ??= (string) $reward->commission_type;

        return $this->matchesRequest($this->requests->settlement($reward), $event, $type, $expectedKey, (int) $reward->settlement_reference_id);
    }

    public function matchesRequest(RewardSettlementRequestData $request, FinanceCommissionEventData $event, ?string $type = null, ?string $expectedKey = null, ?int $originalEventId = null): bool
    {
        $type ??= $request->commission_type;

        return $event->status === 'posted' && $event->commission_type === $type && $event->idempotency_key === ($expectedKey ?? $request->idempotency_key)
            && $event->ib_user_id === $request->beneficiary_user_id && $event->amount_minor === $request->amount_minor
            && $event->minor_units === $request->currency_precision && $event->reference_type === 'reward' && $event->reference_id === $request->reward_id
            && $event->currency_code === $request->currency_code
            && $event->system_wallet_slug === strtolower($request->currency_code).'-main'
            && $event->network_level === $request->network_level
            && ($type !== 'reversal' || $event->reverses_commission_event_id === $originalEventId);
    }
}
