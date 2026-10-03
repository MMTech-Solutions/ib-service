<?php

declare(strict_types=1);

namespace App\Features\Rewards\Actions;

use App\Features\Rewards\DTOs\RewardSettlementRequestData;

final class BuildRewardFinancialRequestAction
{
    public function settlement(object $reward): RewardSettlementRequestData
    {
        if ($reward->settlement_request_snapshot !== null) {
            return new RewardSettlementRequestData(...json_decode($reward->settlement_request_snapshot, true, 512, JSON_THROW_ON_ERROR));
        }

        $legacy = (int) $reward->settlement_attempt_count > 0 || $reward->settlement_reference_id !== null;

        return new RewardSettlementRequestData(
            reward_id: (string) $reward->id,
            beneficiary_user_id: (string) $reward->beneficiary_user_id,
            amount_minor: (int) $reward->amount_minor,
            currency_code: (string) $reward->currency_code,
            currency_precision: (int) $reward->currency_precision,
            idempotency_key: $reward->settlement_idempotency_key ?? 'ib-service:reward:'.$reward->id.':settlement',
            commission_type: (string) $reward->commission_type,
            network_level: $legacy ? (int) $reward->network_level : ($reward->commission_type === 'cpa' ? 1 : (int) $reward->network_level + 1),
        );
    }
}
