<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Strategies;

use App\Features\Rewards\Contracts\Strategies\NegativePnlRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\NegativePnlRewardCalculationData;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;

final class NegativePnlShareCalculationStrategy implements NegativePnlRewardCalculationStrategyInterface
{
    public function calculate(NegativePnlRewardCalculationData $input): ?PositiveMoney
    {
        if (bccomp($input->signed_pnl, '0', self::decimalScale($input->signed_pnl)) >= 0) {
            return null;
        }
        $factors = [ltrim($input->signed_pnl, '-'), $input->level_rate, $input->personal_rate];
        if ($input->is_master) {
            $factors[] = $input->master_rate;
        }
        $scale = array_sum(array_map(self::decimalScale(...), $factors));
        $amount = array_shift($factors);
        foreach ($factors as $factor) {
            $amount = bcmul($amount, $factor, $scale);
        }
        if (bccomp($amount, $input->minimum_amount_major, max($scale, self::decimalScale($input->minimum_amount_major))) < 0) {
            return null;
        }
        if (bccomp($amount, '0', $scale) === 0 || bccomp(bcmul($amount, bcpow('10', (string) $input->currency_precision, 0), max(1, $scale)), '0.5', max(1, $scale)) < 0) {
            return null;
        }
        $money = PositiveMoney::fromDecimalMajorRounded($amount, Currency::from($input->currency_code, $input->currency_precision));

        return $money->minorUnits > 0 ? $money : null;
    }

    private static function decimalScale(string $value): int
    {
        return strlen(explode('.', $value, 2)[1] ?? '');
    }
}
