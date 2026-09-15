<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use App\Features\Rules\Catalog\Exceptions\InvalidRuleConfigurationException;
use App\Features\Rules\Catalog\Exceptions\UnsupportedRuleStrategyException;
use App\Features\Rules\Services\Strategies\ClosedRuleStrategyRegistry;
use App\Features\Rules\Services\Strategies\PointsPerQuantityUnitStrategy;
use PHPUnit\Framework\TestCase;

final class PointsPerQuantityUnitStrategyTest extends TestCase
{
    public function test_it_accepts_canonical_configuration(): void
    {
        $registry = new ClosedRuleStrategyRegistry;
        $registry->validate(PointsPerQuantityUnitStrategy::TYPE, 1, [
            'unit' => 'lot',
            'points_per_unit' => '50.00',
        ]);

        self::assertSame(1, $registry->definition(PointsPerQuantityUnitStrategy::TYPE)->schemaVersion());
    }

    public function test_it_rejects_zero_and_unknown_strategy(): void
    {
        $registry = new ClosedRuleStrategyRegistry;
        $this->expectException(InvalidRuleConfigurationException::class);
        $registry->validate(PointsPerQuantityUnitStrategy::TYPE, 1, [
            'unit' => 'lot',
            'points_per_unit' => '0',
        ]);
    }

    public function test_it_rejects_unregistered_strategies(): void
    {
        $registry = new ClosedRuleStrategyRegistry;
        $this->expectException(UnsupportedRuleStrategyException::class);
        $registry->definition('cpa_fixed_amount');
    }
}
