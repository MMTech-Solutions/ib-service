<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services;

use App\Features\Rewards\DTOs\NegativePnlAggregateData;
use App\Features\Rewards\DTOs\NegativePnlProcessingPeriodData;
use App\Features\Rewards\Exceptions\InvalidNegativePnlPeriodsResponseException;

final class AggregateNegativePnlService
{
    /** @return list<NegativePnlAggregateData> */
    public function execute(NegativePnlProcessingPeriodData $period): array
    {
        $aggregates = [];
        $precisions = [];
        $seen = [];
        foreach ($period->inputs->referrals as $referral) {
            if (! array_key_exists($referral->external_user_id, $period->receipts)) {
                throw InvalidNegativePnlPeriodsResponseException::create();
            }
            foreach ($period->receipts[$referral->external_user_id] as $accountCut) {
                $cut = $accountCut->cut;
                if ($cut->external_user_id !== $referral->external_user_id || isset($seen[$cut->account_id])
                    || (isset($precisions[$cut->currency_code]) && $precisions[$cut->currency_code] !== $cut->currency_precision)) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
                $seen[$cut->account_id] = true;
                $precisions[$cut->currency_code] = $cut->currency_precision;
                if ($accountCut->requires_baseline || $cut->establishesBaseline()) {
                    continue;
                }
                if ($cut->net_pnl === null) {
                    throw InvalidNegativePnlPeriodsResponseException::create();
                }
                if (! collect($period->inputs->configuration->levels)->contains('distribution_level', $referral->distribution_level)) {
                    continue;
                }
                $key = $referral->distribution_level.':'.$cut->currency_code;
                $aggregate = $aggregates[$key] ??= new NegativePnlAggregateData($referral->distribution_level, $cut->currency_code, $cut->currency_precision);
                $scale = max(strlen(explode('.', $cut->net_pnl, 2)[1] ?? ''), strlen(explode('.', $aggregate->signed_pnl, 2)[1] ?? ''), $cut->currency_precision);
                $aggregate->signed_pnl = bcadd($aggregate->signed_pnl, $cut->net_pnl, $scale);
                $aggregate->contributions[] = $cut;
            }
        }
        ksort($aggregates);

        return array_values($aggregates);
    }
}
