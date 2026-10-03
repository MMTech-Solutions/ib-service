<?php

declare(strict_types=1);

namespace App\Features\Rewards\Services\Strategies;

use App\Features\Rewards\Contracts\Strategies\VolumeRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;

final class TradedVolumeCommissionCalculationStrategy implements VolumeRewardCalculationStrategyInterface
{
    public function calculate(VolumeRewardCalculationData $data): ?PositiveMoney
    {
        $source = match ($data->commission_type) {
            'fixed' => $data->quantity,
            'percentage' => $data->broker_granted_commission,
            default => null,
        };
        if ($source === null) {
            return null;
        }

        $factors = [$source, $data->participation_rate, $data->template_level_rate, $data->personal_rate];
        if ($data->is_master) {
            $factors[] = $data->master_rate;
        }
        $scale = array_sum(array_map(self::decimalScale(...), $factors));
        $amount = array_shift($factors);
        foreach ($factors as $factor) {
            $amount = bcmul($amount, $factor, $scale);
        }

        $minimum = $data->minimum_amount_major;
        if (bccomp($amount, $minimum, max($scale, self::decimalScale($minimum))) === -1) {
            return null;
        }

        $money = PositiveMoney::fromDecimalMajorRounded($amount, Currency::from($data->currency_code, $data->currency_precision));

        return $money->minorUnits > 0 ? $money : null;
    }

    private static function decimalScale(string $value): int
    {
        return strlen(explode('.', $value, 2)[1] ?? '');
    }
}
