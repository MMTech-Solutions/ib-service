<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Services\Strategies\CpaFixedAmountStrategy;
use PHPUnit\Framework\TestCase;
use Tests\Support\CpaFixtures;

final class CpaFixedAmountStrategyTest extends TestCase
{
    use CpaFixtures;

    public function test_accepts_independent_points_and_currency_configuration(): void
    {
        $strategy = new CpaFixedAmountStrategy;
        $strategy->validate(1, $this->cpaConfiguration(['00000000-0000-4000-8000-000000000001']));
        self::assertSame(1, $strategy->schemaVersion());
    }

    public function test_rejects_the_old_quantity_threshold_model(): void
    {
        $this->expectException(InvalidRuleConfigurationException::class);
        (new CpaFixedAmountStrategy)->validate(1, ['amount' => '100', 'currency' => 'USD', 'currency_precision' => 2, 'required_volume' => '1', 'required_deposit' => '50', 'volume_unit_code' => 'lot']);
    }

    public function test_rejects_duplicate_modules(): void
    {
        $this->expectException(InvalidRuleConfigurationException::class);
        (new CpaFixedAmountStrategy)->validate(1, $this->cpaConfiguration(['00000000-0000-4000-8000-000000000001', '00000000-0000-4000-8000-000000000001']));
    }

    public function test_rejects_precision_loss(): void
    {
        $configuration = $this->cpaConfiguration(['00000000-0000-4000-8000-000000000001']);
        $configuration['amount'] = '25.001';
        $this->expectException(InvalidRuleConfigurationException::class);
        (new CpaFixedAmountStrategy)->validate(1, $configuration);
    }
}
