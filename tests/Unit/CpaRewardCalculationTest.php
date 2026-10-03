<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaVolumeEvidenceData;
use App\Features\Rewards\Contracts\Strategies\CpaRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\Exceptions\UnsupportedCpaRewardCalculationStrategyException;
use App\Features\Rewards\Factories\CpaRewardCalculationStrategyFactory;
use App\Features\Rewards\Services\Strategies\CpaFixedAmountCalculationStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CpaRewardCalculationTest extends TestCase
{
    /** @return iterable<string, array{string, int, string, int, string, bool}> */
    public static function cases(): iterable
    {
        yield 'empty' => ['0', 0, '0', 0, '0.00000000', false];
        yield 'volume only' => ['2', 0, '0', 0, '2.00000000', false];
        yield 'deposit only' => ['0', 100, '0', 0, '0.00000000', false];
        yield 'exact thresholds' => ['2', 100, '0', 0, '2.00000000', true];
        yield 'incremental' => ['0.00000001', 1, '1.99999999', 99, '2.00000000', true];
        yield 'below threshold' => ['1.99999999', 100, '0', 0, '1.99999999', false];
    }

    #[DataProvider('cases')]
    public function test_calculation_preserves_accumulation(string $quantity, int $deposit, string $initial, int $initialDeposit, string $expected, bool $qualified): void
    {
        $evidence = new CpaEvidenceData([
            new CpaVolumeEvidenceData('position', 'referred', 'closed_volume', 'lot', $quantity, '2026-10-01T00:00:00Z', 'symbol'),
        ], [['amount_minor' => $deposit]]);
        $result = (new CpaFixedAmountCalculationStrategy)->calculate(new CpaRewardCalculationInputData('2', 100, $evidence, $initial, $initialDeposit));

        self::assertSame($expected, $result->volume);
        self::assertSame($deposit + $initialDeposit, $result->deposit_minor);
        self::assertSame($qualified, $result->qualified);
    }

    public function test_empty_evidence_preserves_initial_values(): void
    {
        $result = (new CpaFixedAmountCalculationStrategy)->calculate(new CpaRewardCalculationInputData('2', 100, new CpaEvidenceData([], []), '2', 100));
        self::assertSame('2', $result->volume);
        self::assertTrue($result->qualified);
    }

    public function test_factory_resolves_and_allows_controlled_substitution(): void
    {
        $factory = app(CpaRewardCalculationStrategyFactory::class);
        self::assertInstanceOf(CpaFixedAmountCalculationStrategy::class, $factory->make('cpa_fixed_amount'));
        $replacement = $this->createMock(CpaRewardCalculationStrategyInterface::class);
        app()->instance(CpaFixedAmountCalculationStrategy::class, $replacement);
        self::assertSame($replacement, $factory->make('cpa_fixed_amount'));
    }

    public function test_unknown_strategy_is_rejected(): void
    {
        $this->expectException(UnsupportedCpaRewardCalculationStrategyException::class);
        app(CpaRewardCalculationStrategyFactory::class)->make('negative_pnl_share');
    }
}
