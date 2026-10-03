<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesResultData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionInvalidResponseException;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionNotReadyException;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveClosedVolumeRewardActivityPort;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardDistributionLimitData;
use App\Features\Programs\Contracts\Data\V1\VolumeRewardProgramConfigurationData;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardDistributionLimitPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveVolumeRewardProgramConfigurationPort;
use App\Features\Rewards\Contracts\Data\V1\ResolveRewardUplineResultData;
use App\Features\Rewards\Contracts\Data\V1\RewardUplineBeneficiaryData;
use App\Features\Rewards\Contracts\Ports\Output\ResolveRewardUplinePort;
use App\Features\Rewards\Contracts\Strategies\VolumeRewardCalculationStrategyInterface;
use App\Features\Rewards\DTOs\PersistVolumeRewardData;
use App\Features\Rewards\DTOs\VolumeRewardCalculationData;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rewards\Factories\VolumeRewardCalculationStrategyFactory;
use App\Features\Rewards\Factories\VolumeRewardProcessingRepositoryFactory;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use App\Features\Rewards\Repositories\VolumeRewardProcessingRepositoryInterface;
use App\Features\Rewards\Services\Strategies\TradedVolumeCommissionCalculationStrategy;
use App\Features\Rewards\UseCases\ProcessVolumeRewardsUseCase;
use App\Features\Rules\Contracts\Data\V1\VolumeRewardRuleContextData;
use App\Features\Rules\Contracts\Ports\Input\ResolveVolumeRewardRuleContextPort;
use App\Features\SharedKernel\ValueObjects\Currency;
use App\Features\SharedKernel\ValueObjects\PositiveMoney;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveRewardBackfillStartData;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextResultData;
use App\Features\Subscriptions\Contracts\Data\V1\SubscriptionContextData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveRewardBackfillStartPort;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProcessVolumeRewardsUseCaseTest extends TestCase
{
    public function test_a_not_ready_event_is_scheduled_for_retry(): void
    {
        config()->set('rewards.volume.broker_module_id', null);
        $receipt = (object) ['id' => 'receipt-1', 'module_id' => 'module-1', 'order_id' => 'order-1', 'external_trader_id' => 'login-1', 'claim_token' => 'token-1'];
        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $processing->shouldReceive('claimNextEvent')->twice()->andReturn($receipt, null);
        $processing->shouldReceive('markEventRetryable')->once()->with('receipt-1', 'token-1', 'BROKER_CLOSED_POSITION_NOT_READY', Mockery::type('object'));
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);
        $closed = Mockery::mock(ResolveClosedVolumeRewardActivityPort::class);
        $closed->shouldReceive('execute')->once()->andThrow(BrokerClosedPositionNotReadyException::create());

        $result = $this->useCaseWith($closed)->execute(10);

        self::assertSame(1, $result['event_retryable']);
        self::assertSame(0, $result['event_rejected']);
    }

    public function test_an_invalid_broker_contract_is_rejected_permanently(): void
    {
        config()->set('rewards.volume.broker_module_id', null);
        $receipt = (object) ['id' => 'receipt-1', 'module_id' => 'module-1', 'order_id' => 'order-1', 'external_trader_id' => 'login-1', 'claim_token' => 'token-1'];
        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $processing->shouldReceive('claimNextEvent')->twice()->andReturn($receipt, null);
        $processing->shouldReceive('markEventRejected')->once()->with('receipt-1', 'token-1', 'BROKER_CLOSED_POSITION_INVALID_RESPONSE', Mockery::type('object'));
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);
        $closed = Mockery::mock(ResolveClosedVolumeRewardActivityPort::class);
        $closed->shouldReceive('execute')->once()->andThrow(BrokerClosedPositionInvalidResponseException::create());

        $result = $this->useCaseWith($closed)->execute(10);

        self::assertSame(0, $result['event_retryable']);
        self::assertSame(1, $result['event_rejected']);
    }

    /** @return iterable<string, array{bool}> */
    public static function calculationImplementations(): iterable
    {
        yield 'real calculation' => [false];
        yield 'substituted calculation' => [true];
    }

    #[DataProvider('calculationImplementations')]
    public function test_event_and_periodic_paths_share_the_same_economic_idempotency_key(bool $substitute): void
    {
        config()->set('rewards.minimum_amount_major', '0.02');
        if ($substitute) {
            $calculation = Mockery::mock(VolumeRewardCalculationStrategyInterface::class);
            $calculation->shouldReceive('calculate')->twice()->withArgs(function (VolumeRewardCalculationData $input): bool {
                return $input->minimum_amount_major === '0.02' && $input->quantity === '2'
                    && $input->participation_rate === '0.25' && $input->template_level_rate === '0.5';
            })->andReturn(PositiveMoney::fromDecimalMajorRounded('1.23', Currency::from('USD', 2)));
            app()->instance(TradedVolumeCommissionCalculationStrategy::class, $calculation);
        }
        $moduleId = '00000000-0000-7000-8000-000000000001';
        config()->set('rewards.volume.broker_module_id', $moduleId);
        $receipt = (object) ['id' => 'receipt-1', 'module_id' => $moduleId, 'order_id' => 'order-1', 'external_trader_id' => 'login-1', 'claim_token' => 'receipt-token'];
        $run = (object) ['id' => 'run-1', 'occurred_from' => '2026-10-01T00:00:00+00:00', 'occurred_until' => '2026-10-02T00:00:00+00:00', 'cursor' => null, 'claim_token' => 'run-token'];
        $activity = new VolumeRewardActivityData($moduleId, 'position-1', '00000000-0000-7000-8000-000000000002', 'lot', '2', '2026-10-01T12:00:00+00:00', 'broker:server_group:group-1:symbol:symbol-1', 'USD', 2, null);

        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $processing->shouldReceive('claimNextEvent')->twice()->andReturn($receipt, null);
        $processing->shouldReceive('markEventProcessed')->once();
        $processing->shouldReceive('claimPeriodicRun')->once()->andReturn($run);
        $processing->shouldReceive('completePeriodicPage')->once()->with('run-1', 'run-token', null, Mockery::type('object'));
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);

        $persistedKeys = [];
        $rewards = Mockery::mock(RewardRepositoryInterface::class);
        $rewards->shouldReceive('persistVolumeReward')->twice()->withArgs(function (PersistVolumeRewardData $data) use (&$persistedKeys, $substitute): bool {
            $persistedKeys[] = $data->origin_idempotency_key;
            self::assertSame($substitute ? 123 : 25, $data->amount_minor);
            self::assertSame('plan-1', $data->plan_id);
            self::assertSame('program-1', $data->program_id);
            self::assertSame('version-1', $data->rule_version_id);

            return true;
        })->andReturn(true, false);
        app()->instance('rewards.repositories.postgresql', $rewards);

        $closed = Mockery::mock(ResolveClosedVolumeRewardActivityPort::class);
        $closed->shouldReceive('execute')->once()->andReturn($activity);
        $activities = Mockery::mock(ListVolumeRewardActivitiesPort::class);
        $activities->shouldReceive('execute')->once()->andReturn(new ListVolumeRewardActivitiesResultData($moduleId, 'running', true, [$activity], null, null));
        $limit = Mockery::mock(ResolveVolumeRewardDistributionLimitPort::class);
        $limit->shouldReceive('execute')->times(3)->andReturn(new VolumeRewardDistributionLimitData([$activity->instrument_reference], 0));
        $program = Mockery::mock(ResolveVolumeRewardProgramConfigurationPort::class);
        $program->shouldReceive('execute')->twice()->andReturn(new VolumeRewardProgramConfigurationData(null, 'symbol-config-1', 'both', 'payment-binding-1', 'payment-version-1', 'fixed', '0.25', 0, '0.5', 'USD'));
        $upline = Mockery::mock(ResolveRewardUplinePort::class);
        $upline->shouldReceive('resolve')->twice()->andReturn(ResolveRewardUplineResultData::resolved([
            new RewardUplineBeneficiaryData('00000000-0000-7000-8000-000000000003', 0),
        ], '2026-10-02T00:00:00+00:00'));
        $subscriptions = Mockery::mock(ResolveSubscriptionContextPort::class);
        $subscriptions->shouldReceive('resolve')->twice()->withArgs(function ($query) use ($activity): bool {
            return $query->occurred_at === $activity->occurred_at;
        })->andReturn(ResolveSubscriptionContextResultData::foundContext(new SubscriptionContextData(
            'subscription-1', 'plan-1', 'program-1', 'placement-1', 'unfixed', '2026-09-01T00:00:00+00:00', '1', false, '1',
        )));
        $backfill = Mockery::mock(ResolveRewardBackfillStartPort::class);
        $backfill->shouldReceive('execute')->once()->andReturn(new ResolveRewardBackfillStartData('2026-10-01T00:00:00+00:00'));
        $rules = Mockery::mock(ResolveVolumeRewardRuleContextPort::class);
        $rules->shouldReceive('execute')->twice()->andReturn(new VolumeRewardRuleContextData('assignment-1', 'rule-1', 'version-1'));

        $useCase = new ProcessVolumeRewardsUseCase(
            app(VolumeRewardProcessingRepositoryFactory::class),
            app(RewardRepositoryFactory::class),
            $closed,
            $activities,
            $limit,
            $program,
            $upline,
            $subscriptions,
            $backfill,
            $rules,
            app(VolumeRewardCalculationStrategyFactory::class),
        );

        $result = $useCase->execute(10);

        self::assertSame(1, $result['rewards_created']);
        self::assertSame(1, $result['rewards_skipped']);
        self::assertCount(2, $persistedKeys);
        self::assertSame($persistedKeys[0], $persistedKeys[1]);
    }

    private function useCaseWith(ResolveClosedVolumeRewardActivityPort $closed): ProcessVolumeRewardsUseCase
    {
        return new ProcessVolumeRewardsUseCase(
            app(VolumeRewardProcessingRepositoryFactory::class),
            app(RewardRepositoryFactory::class),
            $closed,
            Mockery::mock(ListVolumeRewardActivitiesPort::class),
            Mockery::mock(ResolveVolumeRewardDistributionLimitPort::class),
            Mockery::mock(ResolveVolumeRewardProgramConfigurationPort::class),
            Mockery::mock(ResolveRewardUplinePort::class),
            Mockery::mock(ResolveSubscriptionContextPort::class),
            Mockery::mock(ResolveRewardBackfillStartPort::class),
            Mockery::mock(ResolveVolumeRewardRuleContextPort::class),
            app(VolumeRewardCalculationStrategyFactory::class),
        );
    }
}
