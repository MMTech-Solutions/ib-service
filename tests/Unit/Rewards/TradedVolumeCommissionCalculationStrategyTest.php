<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Features\Rewards\Contracts\Strategies\VolumeRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use App\Features\Rewards\Exceptions\UnsupportedVolumeRewardCalculationStrategyException;
use App\Features\Rewards\Factories\VolumeRewardCalculationStrategyFactory;
use App\Features\Rewards\Services\Strategies\TradedVolumeCommissionCalculationStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TradedVolumeCommissionCalculationStrategyTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, int, ?int}> */
    public static function boundaries(): iterable
    {
        yield 'minimum equality' => ['fixed', '0.01', '0.01', 2, 1];
        yield 'below minimum before rounding' => ['fixed', '0.00999999', '0.01', 2, null];
        yield 'eight decimal precision' => ['fixed', '1.23456789', '0.01', 8, 123456789];
        yield 'rounds to zero' => ['fixed', '0.001', '0.001', 2, null];
        yield 'percentage zero' => ['percentage', '0', '0.01', 2, null];
        yield 'unknown commission' => ['unsupported', '2', '0.01', 2, null];
    }

    #[DataProvider('boundaries')]
    public function test_boundaries(string $type, string $base, string $minimum, int $precision, ?int $expected): void
    {
        $result = (new TradedVolumeCommissionCalculationStrategy)->calculate(new VolumeRewardCalculationData(
            $type, $base, $base, '1', '1', '1', false, '99', 'USD', $precision, $minimum,
        ));
        self::assertSame($expected, $result?->minorUnits);
    }

    public function test_factory_resolution_and_controlled_substitution(): void
    {
        $factory = app(VolumeRewardCalculationStrategyFactory::class);
        self::assertInstanceOf(TradedVolumeCommissionCalculationStrategy::class, $factory->make('traded_volume_commission'));
        $replacement = $this->createMock(VolumeRewardCalculationStrategyInterface::class);
        app()->instance(TradedVolumeCommissionCalculationStrategy::class, $replacement);
        self::assertSame($replacement, $factory->make('traded_volume_commission'));
    }

    public function test_unknown_strategy_code_is_rejected(): void
    {
        $this->expectException(UnsupportedVolumeRewardCalculationStrategyException::class);
        app(VolumeRewardCalculationStrategyFactory::class)->make('volume');
    }

    public function test_it_calculates_fixed_volume_with_subscription_rates_and_rounding(): void
    {
        $money = (new TradedVolumeCommissionCalculationStrategy)->calculate(new VolumeRewardCalculationData(
            'fixed', '2', '0', '0.25', '0.5', '0.8', true, '1.5', 'USD', 2, '0.01',
        ));

        self::assertSame(30, $money?->minorUnits);
    }

    public function test_it_calculates_percentage_from_broker_granted_commission(): void
    {
        $money = (new TradedVolumeCommissionCalculationStrategy)->calculate(new VolumeRewardCalculationData(
            'percentage', '99', '2', '0.25', '1', '1', false, '99', 'USD', 2, '0.01',
        ));

        self::assertSame(50, $money?->minorUnits);
    }

    public function test_it_skips_rewards_below_the_global_threshold(): void
    {
        $money = (new TradedVolumeCommissionCalculationStrategy)->calculate(new VolumeRewardCalculationData(
            'fixed', '0.001', '0', '1', '1', '1', false, '1', 'USD', 2, '0.01',
        ));

        self::assertNull($money);
    }

    public function test_it_rounds_half_up_once_at_currency_precision(): void
    {

        $money = (new TradedVolumeCommissionCalculationStrategy)->calculate(new VolumeRewardCalculationData(
            'fixed', '1', '0', '0.005', '1', '1', false, '1', 'USD', 2, '0.001',
        ));

        self::assertSame(1, $money?->minorUnits);
    }
}
