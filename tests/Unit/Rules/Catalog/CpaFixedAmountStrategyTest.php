<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Services\Strategies\ClosedRuleStrategyRegistry;
use App\Features\Rules\Services\Strategies\CpaFixedAmountStrategy;
use PHPUnit\Framework\TestCase;

final class CpaFixedAmountStrategyTest extends TestCase
{
    public function test_it_accepts_the_current_cpa_configuration_with_explicit_currency_precision(): void
    {
        $registry = new ClosedRuleStrategyRegistry;

        $registry->validate(CpaFixedAmountStrategy::TYPE, 1, [
            'amount' => '100.00',
            'currency' => 'USD',
            'currency_precision' => 2,
            'required_deposit' => '50.00',
            'required_volume' => '1.00',
            'volume_unit_code' => 'lot',
        ]);

        self::assertSame(1, $registry->definition(CpaFixedAmountStrategy::TYPE)->schemaVersion());
    }

    public function test_it_rejects_a_current_cpa_configuration_without_currency_precision(): void
    {
        $registry = new ClosedRuleStrategyRegistry;

        $this->expectException(InvalidRuleConfigurationException::class);
        $registry->validate(CpaFixedAmountStrategy::TYPE, 1, [
            'amount' => '100.00',
            'currency' => 'USD',
            'required_deposit' => '50.00',
            'required_volume' => '1.00',
            'volume_unit_code' => 'lot',
        ]);
    }

    public function test_it_rejects_a_legacy_cpa_schema_shape(): void
    {
        $registry = new ClosedRuleStrategyRegistry;

        $this->expectException(InvalidRuleConfigurationException::class);
        $registry->validate(CpaFixedAmountStrategy::TYPE, 1, [
            'amount' => '100.00',
            'currency' => 'USD',
            'required_deposit' => '50.00',
            'required_volume' => '1.00',
            'volume_unit_code' => 'lot',
        ]);
    }
}
