<?php

declare(strict_types=1);

namespace App\Features\Rewards\Actions;

use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;

final class CalculateVolumeRewardAmountAction
{
    public function execute(VolumeRewardCalculationData $data): ?PositiveMoney
    {
        $source = match ($data->commission_type) {
            'fixed' => $data->quantity,
            'percentage' => $data->broker_granted_commission,
            default => null,
        };
        if ($source === null) {
            return null;
        }

        $amount = bcmul($source, $data->participation_rate, 12);
        $amount = bcmul($amount, $data->template_level_rate, 12);
        $amount = bcmul($amount, $data->personal_rate, 12);
        if ($data->is_master) {
            $amount = bcmul($amount, $data->master_rate, 12);
        }
        if (bccomp($amount, (string) config('rewards.minimum_amount_major', '0.01'), 12) === -1) {
            return null;
        }

        $money = PositiveMoney::fromDecimalMajorRounded($amount, Currency::from($data->currency_code, $data->currency_precision));

        return $money->minorUnits > 0 ? $money : null;
    }
}
