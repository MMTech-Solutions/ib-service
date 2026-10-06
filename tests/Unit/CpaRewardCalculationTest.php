<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Modules\Contracts\Data\V1\CertifiedDepositEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaVolumeEvidenceData;
use App\Features\Rewards\DTOs\CpaRewardCalculationInputData;
use App\Features\Rewards\Exceptions\CpaEvidenceContractException;
use App\Features\Rewards\Factories\CpaRewardCalculationStrategyFactory;
use App\Features\Rewards\Services\Strategies\CpaFixedAmountCalculationStrategy;
use Tests\Support\CpaFixtures;
use Tests\TestCase;

final class CpaRewardCalculationTest extends TestCase
{
    use CpaFixtures;

    public function test_independent_thresholds_and_real_quantities_are_preserved(): void
    {
        $config = $this->cpaConfiguration(['00000000-0000-4000-8000-000000000001']);
        $evidence = new CpaEvidenceData([new CpaVolumeEvidenceData('position', 'user', 'closed_trading_volume', 'lot', '2', '2026-10-01T00:00:00Z', 'symbol')], [new CertifiedDepositEvidenceData('deposit', 'user', 30000, 'USD', '2026-10-01T00:00:00Z')]);
        $strategy = new CpaFixedAmountCalculationStrategy;
        $result = $strategy->calculate(new CpaRewardCalculationInputData($config, $evidence, '20'));
        self::assertSame('40.0000000000000000', $result->volume_points);
        self::assertSame('60.0000000000000000', $result->deposit_points);
        self::assertFalse($result->qualified);
        self::assertSame('2', $result->contributions[0]->quantity);
        self::assertSame(30000, $result->contributions[1]->amount_minor);
        $total = $strategy->calculate(new CpaRewardCalculationInputData($config, new CpaEvidenceData([], []), initial_volume_points: '100', initial_deposit_points: '100'));
        self::assertTrue($total->qualified);
        self::assertFalse($strategy->calculate(new CpaRewardCalculationInputData($config, new CpaEvidenceData([], []), initial_volume_points: '1000', initial_deposit_points: '99.9999999999999999'))->qualified);
    }

    public function test_multiplication_keeps_sixteen_decimals(): void
    {
        $result = (new CpaFixedAmountCalculationStrategy)->calculate(new CpaRewardCalculationInputData($this->cpaConfiguration([]), new CpaEvidenceData([new CpaVolumeEvidenceData('p', 'u', 'closed_volume', 'lot', '0.00000001', '2026-10-01T00:00:00Z', 's')], []), '0.00000001'));
        self::assertSame('0.0000000000000001', $result->volume_points);
    }

    public function test_invalid_quantity_is_rejected_without_truncation(): void
    {
        $this->expectException(CpaEvidenceContractException::class);
        (new CpaFixedAmountCalculationStrategy)->calculate(new CpaRewardCalculationInputData($this->cpaConfiguration([]), new CpaEvidenceData([new CpaVolumeEvidenceData('p', 'u', 'closed_volume', 'lot', '0.000000001', '2026-10-01T00:00:00Z', 's')], []), '1'));
    }

    public function test_factory_resolves_current_strategy(): void
    {
        self::assertInstanceOf(CpaFixedAmountCalculationStrategy::class, app(CpaRewardCalculationStrategyFactory::class)->make('cpa_fixed_amount'));
    }
}
