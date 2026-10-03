<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Rewards\Contracts\Strategies\NegativePnlRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\NegativePnlRewardCalculationData;
use App\Features\Rewards\Exceptions\UnsupportedNegativePnlRewardCalculationStrategyException;
use App\Features\Rewards\Factories\NegativePnlRewardCalculationStrategyFactory;
use App\Features\Rewards\Services\Strategies\NegativePnlShareCalculationStrategy;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NegativePnlRewardCalculationTest extends TestCase
{
    #[DataProvider('calculations')]
    public function test_exact_economics(string $pnl, string $rate, string $personal, bool $master, string $masterRate, int $precision, string $minimum, ?int $expected): void
    {
        $input = new NegativePnlRewardCalculationData($pnl, $rate, $personal, $master, $masterRate, 'USD', $precision, $minimum);
        $result = app(NegativePnlRewardCalculationStrategyFactory::class)->make('negative_pnl_share')->calculate($input);
        self::assertSame($expected, $result?->minorUnits);
    }

    /** @return iterable<string,array{string,string,string,bool,string,int,string,?int}> */
    public static function calculations(): iterable
    {
        yield 'negative' => ['-100', '0.1', '1', false, '2', 2, '0.01', 1000];
        yield 'master' => ['-100', '0.1', '0.5', true, '0.3', 2, '0.01', 150];
        yield 'zero' => ['0', '1', '1', false, '1', 2, '0.01', null];
        yield 'positive' => ['100', '1', '1', false, '1', 2, '0.01', null];
        yield 'zero level' => ['-100', '0', '1', false, '1', 2, '0', null];
        yield 'zero personal' => ['-100', '1', '0', false, '1', 2, '0', null];
        yield 'zero master' => ['-100', '1', '1', true, '0', 2, '0', null];
        yield 'minimum equal' => ['-0.01', '1', '1', false, '1', 2, '0.01', 1];
        yield 'below minimum before round' => ['-0.009999', '1', '1', false, '1', 2, '0.01', null];
        yield 'round zero' => ['-0.004', '1', '1', false, '1', 2, '0', null];
        yield 'tiny rounds zero' => ['-0.00000000000001', '1', '1', false, '1', 2, '0', null];
        yield 'half up' => ['-0.005', '1', '1', false, '1', 2, '0', 1];
        yield 'precise product' => ['-0.0000001', '0.5', '0.2', false, '1', 8, '0.00000001', 1];
        yield 'precision greater than eight' => ['-0.000000001', '1', '1', false, '1', 9, '0', 1];
        yield 'no intermediate round' => ['-0.015', '0.5', '0.5', false, '1', 2, '0', null];
    }

    public function test_factory_can_substitute_calculation_without_environment_branches(): void
    {
        $fake = new class implements NegativePnlRewardCalculationStrategyInterface
        {
            public function calculate(NegativePnlRewardCalculationData $input): ?PositiveMoney
            {
                return null;
            }
        };
        $this->app->instance(NegativePnlShareCalculationStrategy::class, $fake);
        self::assertSame($fake, app(NegativePnlRewardCalculationStrategyFactory::class)->make('negative_pnl_share'));
    }

    public function test_unknown_strategy_is_rejected(): void
    {
        $this->expectException(UnsupportedNegativePnlRewardCalculationStrategyException::class);
        app(NegativePnlRewardCalculationStrategyFactory::class)->make('pnl');
    }
}
