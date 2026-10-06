<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Strategies;

use App\Features\Rewards\Contracts\Strategies\CpaRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\CpaContributionData;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\DTOs\CpaRewardCalculationResultData;
use App\Features\Rewards\Exceptions\CpaEvidenceContractException;

final class CpaFixedAmountCalculationStrategy implements CpaRewardCalculationStrategyInterface
{
    public function calculate(CpaRewardCalculationInputData $input): CpaRewardCalculationResultData
    {
        $volume = $input->initial_volume_points;
        $deposit = $input->initial_deposit_points;
        $contributions = [];
        foreach ($input->evidence->volume_facts as $fact) {
            if ($fact->unit_code !== 'lot' || ! in_array($fact->metric_code, ['closed_volume', 'closed_trading_volume'], true)) {
                throw new CpaEvidenceContractException('Unexpected CPA volume metric.');
            }
            $this->quantity($fact->quantity);
            $points = bcmul($fact->quantity, $input->points_per_volume_unit, 16);
            $volume = bcadd($volume, $points, 16);
            $contributions[] = new CpaContributionData($fact->provider, $fact->source_activity_id, $fact->subject_external_user_id, 'volume', $fact->quantity, 'lot', $input->points_per_volume_unit, $points, $fact->occurred_at, $fact->instrument_reference);
        }
        foreach ($input->evidence->deposit_facts as $fact) {
            if ($fact->amount_minor <= 0 || $fact->currency_code !== $input->configuration['deposit_currency']) {
                throw new CpaEvidenceContractException('Unexpected CPA deposit.');
            }
            $precision = $input->configuration['deposit_currency_precision'];
            $quantity = bcdiv((string) $fact->amount_minor, bcpow('10', (string) $precision, 0), $precision);
            $points = bcmul($quantity, $input->configuration['deposit_points_per_unit'], 16);
            $deposit = bcadd($deposit, $points, 16);
            $contributions[] = new CpaContributionData($fact->provider, $fact->source_activity_id, $fact->subject_external_user_id, 'deposit', $quantity, $fact->currency_code, $input->configuration['deposit_points_per_unit'], $points, $fact->occurred_at, amount_minor: $fact->amount_minor, currency_code: $fact->currency_code);
        }

        return new CpaRewardCalculationResultData($volume, $deposit, bccomp($volume, $input->configuration['required_volume_points'], 16) >= 0 && bccomp($deposit, $input->configuration['required_deposit_points'], 16) >= 0, $contributions);
    }

    private function quantity(string $quantity): void
    {
        if (preg_match('/^(0|[1-9][0-9]{0,15})(\\.[0-9]{1,8})?$/D', $quantity) !== 1 || bccomp($quantity, '0', 8) <= 0) {
            throw new CpaEvidenceContractException('Invalid volume quantity.');
        }
    }
}
