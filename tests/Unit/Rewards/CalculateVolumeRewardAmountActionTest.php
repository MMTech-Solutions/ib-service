<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Features\Rewards\Actions\CalculateVolumeRewardAmountAction;
use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use Tests\TestCase;

final class CalculateVolumeRewardAmountActionTest extends TestCase
{
    public function test_it_calculates_fixed_volume_with_subscription_rates_and_rounding(): void
    {
        $money = (new CalculateVolumeRewardAmountAction)->execute(new VolumeRewardCalculationData(
            'fixed', '2', '0', '0.25', '0.5', '0.8', true, '1.5', 'USD', 2,
        ));

        self::assertSame(30, $money?->minorUnits);
    }

    public function test_it_calculates_percentage_from_broker_granted_commission(): void
    {
        $money = (new CalculateVolumeRewardAmountAction)->execute(new VolumeRewardCalculationData(
            'percentage', '99', '2', '0.25', '1', '1', false, '99', 'USD', 2,
        ));

        self::assertSame(50, $money?->minorUnits);
    }

    public function test_it_skips_rewards_below_the_global_threshold(): void
    {
        $money = (new CalculateVolumeRewardAmountAction)->execute(new VolumeRewardCalculationData(
            'fixed', '0.001', '0', '1', '1', '1', false, '1', 'USD', 2,
        ));

        self::assertNull($money);
    }
}
