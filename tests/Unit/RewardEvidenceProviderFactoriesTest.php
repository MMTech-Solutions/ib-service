<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Modules\Catalog\Contracts\Strategies\CpaEvidenceProviderStrategyInterface;
use App\Features\Modules\Catalog\Contracts\Strategies\VolumeRewardActivitiesProviderStrategyInterface;
use App\Features\Modules\Catalog\Exceptions\UnsupportedRewardEvidenceProviderException;
use App\Features\Modules\Catalog\Factories\CpaEvidenceProviderFactory;
use App\Features\Modules\Catalog\Factories\VolumeRewardActivitiesProviderFactory;
use App\Features\Modules\Sources\Broker\Services\Strategies\BrokerCpaEvidenceProviderStrategy;
use App\Features\Modules\Sources\Broker\Services\Strategies\BrokerVolumeRewardActivitiesProviderStrategy;
use App\Features\Rewards\Contracts\Ports\Output\ResolveNegativePnlPeriodsPort;
use App\Features\Rewards\Exceptions\UnsupportedNegativePnlPeriodsProviderException;
use App\Features\Rewards\Factories\NegativePnlPeriodsProviderFactory;
use App\Features\Rewards\Services\Adapters\BrokerResolveNegativePnlPeriodsAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RewardEvidenceProviderFactoriesTest extends TestCase
{
    /** @return iterable<string, array{class-string, class-string, class-string, class-string}> */
    public static function providers(): iterable
    {
        yield 'CPA' => [CpaEvidenceProviderFactory::class, BrokerCpaEvidenceProviderStrategy::class, CpaEvidenceProviderStrategyInterface::class, UnsupportedRewardEvidenceProviderException::class];
        yield 'volume page' => [VolumeRewardActivitiesProviderFactory::class, BrokerVolumeRewardActivitiesProviderStrategy::class, VolumeRewardActivitiesProviderStrategyInterface::class, UnsupportedRewardEvidenceProviderException::class];
        yield 'PnL' => [NegativePnlPeriodsProviderFactory::class, BrokerResolveNegativePnlPeriodsAdapter::class, ResolveNegativePnlPeriodsPort::class, UnsupportedNegativePnlPeriodsProviderException::class];
    }

    #[DataProvider('providers')]
    public function test_resolution_and_substitution(string $factory, string $implementation, string $contract, string $exception): void
    {
        $resolver = app($factory);
        self::assertInstanceOf($implementation, $resolver->make('broker'));
        $replacement = $this->createMock($contract);
        app()->instance($implementation, $replacement);
        self::assertSame($replacement, $resolver->make('broker'));
    }

    #[DataProvider('providers')]
    public function test_unknown_code_is_rejected(string $factory, string $implementation, string $contract, string $exception): void
    {
        $this->expectException($exception);
        app($factory)->make('unknown');
    }

    public function test_pnl_port_binding_selects_through_the_factory(): void
    {
        app()->forgetInstance(ResolveNegativePnlPeriodsPort::class);
        $replacement = $this->createMock(ResolveNegativePnlPeriodsPort::class);
        app()->instance(BrokerResolveNegativePnlPeriodsAdapter::class, $replacement);
        self::assertSame($replacement, app(ResolveNegativePnlPeriodsPort::class));
    }
}
