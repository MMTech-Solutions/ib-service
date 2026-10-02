<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Services\Strategies\ClosedRuleStrategyRegistry;
use App\Features\Rules\Services\Strategies\NegativePnlShareStrategy;
use App\Features\Rules\Services\Strategies\TradedVolumeCommissionStrategy;
use Tests\TestCase;

final class Rwd4RuleStrategiesTest extends TestCase
{
    public function test_it_accepts_the_closed_volume_rule_configuration(): void
    {
        $registry = new ClosedRuleStrategyRegistry;

        $registry->validate(TradedVolumeCommissionStrategy::TYPE, 1, ['unit_code' => 'lot']);

        self::assertSame(1, $registry->definition(TradedVolumeCommissionStrategy::TYPE)->schemaVersion());
    }

    public function test_it_rejects_a_volume_rule_with_an_unsupported_unit(): void
    {
        $this->expectException(InvalidRuleConfigurationException::class);

        (new ClosedRuleStrategyRegistry)->validate(TradedVolumeCommissionStrategy::TYPE, 1, ['unit_code' => 'usd']);
    }

    public function test_it_requires_a_payment_template_binding_for_negative_pnl(): void
    {
        $registry = new ClosedRuleStrategyRegistry;

        $registry->validate(NegativePnlShareStrategy::TYPE, 1, [
            'plan_payment_template_version_binding_id' => '00000000-0000-4000-8000-000000000001',
        ]);

        self::assertSame(1, $registry->definition(NegativePnlShareStrategy::TYPE)->schemaVersion());
    }
}
