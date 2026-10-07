<?php

declare(strict_types=1);

namespace Tests\Unit\Rewards;

use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesResultData;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardActivityData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use App\Features\Modules\Contracts\Ports\Input\ResolveVolumeRewardModulesPort;
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
use App\Features\Rewards\DTOs\VolumeRewardEvaluationData;
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
    public function test_a_paused_module_defers_a_complete_event_without_querying_the_provider(): void
    {
        $activity = new VolumeRewardActivityData('module-1', 'position-1', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'lot', '2', '2026-10-01T12:00:00Z', 'symbol', 'USD', 2, '3');
        $receipt = (object) ['id' => 'receipt-1', 'module_id' => 'module-1', 'activity' => json_encode($activity->toArray()), 'claim_token' => 'token-1'];
        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $processing->shouldReceive('claimNextEvent')->twice()->andReturn($receipt, null);
        $processing->shouldReceive('markEventRetryable')->once()->with('receipt-1', 'token-1', 'module_not_operational', Mockery::type('object'));
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);
        $modules = Mockery::mock(ResolveVolumeRewardModulesPort::class);
        $modules->shouldReceive('execute')->with('module-1')->once()->andReturn([new ModuleSummaryData('module-1', 'broker', 'Broker', true, 'paused')]);
        $modules->shouldReceive('execute')->withNoArgs()->once()->andReturn([]);
        $result = $this->useCaseWith($modules)->execute(10);

        self::assertSame(1, $result['event_retryable']);
        self::assertSame(0, $result['event_rejected']);
    }

    public function test_conflicting_frozen_evidence_rejects_the_receipt(): void
    {
        $activity = new VolumeRewardActivityData('module-1', 'position-1', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'lot', '2', '2026-10-01T12:00:00Z', 'symbol', 'USD', 2, '3');
        $receipt = (object) ['id' => 'receipt-1', 'module_id' => 'module-1', 'activity' => json_encode($activity->toArray()), 'claim_token' => 'token-1'];
        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $processing->shouldReceive('claimNextEvent')->twice()->andReturn($receipt, null);
        $processing->shouldReceive('claimEvaluation')->once()->andThrow(InvalidVolumeRewardActivityException::create());
        $processing->shouldReceive('markEventRejected')->once()->with('receipt-1', 'token-1', 'INVALID_VOLUME_REWARD_ACTIVITY', Mockery::type('object'));
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);
        $modules = Mockery::mock(ResolveVolumeRewardModulesPort::class);
        $modules->shouldReceive('execute')->with('module-1')->once()->andReturn([new ModuleSummaryData('module-1', 'broker', 'Provider', true, 'running')]);
        $modules->shouldReceive('execute')->withNoArgs()->once()->andReturn([]);
        $result = $this->useCaseWith($modules)->execute(10);

        self::assertSame(0, $result['event_retryable']);
        self::assertSame(1, $result['event_rejected']);
    }

    /** @return iterable<string, array{bool, string, string}> */
    public static function calculationImplementations(): iterable
    {
        yield 'real Broker fixed' => [false, 'broker', 'fixed'];
        yield 'real Copy Trading fixed' => [false, 'copy_trading', 'fixed'];
        yield 'real Copy Trading percentage' => [false, 'copy_trading', 'percentage'];
        yield 'substituted calculation' => [true, 'broker', 'fixed'];
    }

    #[DataProvider('calculationImplementations')]
    public function test_event_and_periodic_paths_share_the_same_economic_idempotency_key(bool $substitute, string $provider, string $commissionType): void
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
        $run = (object) ['id' => 'run-1', 'occurred_from' => '2026-10-01T00:00:00+00:00', 'occurred_until' => '2026-10-02T00:00:00+00:00', 'cursor' => null, 'claim_token' => 'run-token'];
        $activity = new VolumeRewardActivityData($moduleId, 'position-1', '00000000-0000-7000-8000-000000000002', 'lot', '2', '2026-10-01T12:00:00+00:00', $provider.':server_group:group-1:symbol:symbol-1', 'USD', 2, '3');

        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $receipt = (object) ['id' => 'receipt-1', 'module_id' => $activity->module_id, 'activity' => json_encode($activity->toArray()), 'claim_token' => 'receipt-token'];
        $processing->shouldReceive('claimNextEvent')->twice()->andReturn($receipt, null);
        $processing->shouldReceive('markEventProcessed')->once();
        $processing->shouldReceive('claimPeriodicRun')->once()->andReturn($run);
        $processing->shouldReceive('completePeriodicPage')->once()->with('run-1', 'run-token', null, Mockery::type('object'));
        $distribution = null;
        $preparations = [];
        $processing->shouldReceive('claimEvaluation')->twice()->andReturnUsing(function () use ($activity, &$distribution, &$preparations) {
            return new VolumeRewardEvaluationData('evaluation', 'token', $activity, $distribution, $preparations);
        });
        $processing->shouldReceive('freezeDistribution')->once()->andReturnUsing(function ($evaluation, $value) use (&$distribution): void {
            $distribution = $value;
        });
        $processing->shouldReceive('freezePreparation')->twice()->andReturnUsing(function ($evaluation, $channel, $value) use (&$preparations): void {
            $preparations[$channel] = $value;
        });
        $processing->shouldReceive('evaluationTransaction')->twice()->andReturnUsing(fn ($evaluation, $callback) => $callback());
        $processing->shouldReceive('recordEvaluationOutcome')->times(4);
        $processing->shouldReceive('releaseEvaluation')->twice();
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);

        $persistedKeys = [];
        $rewards = Mockery::mock(RewardRepositoryInterface::class);
        $rewards->shouldReceive('persistVolumeReward')->twice()->withArgs(function (PersistVolumeRewardData $data) use (&$persistedKeys, $substitute, $commissionType): bool {
            $persistedKeys[] = $data->origin_idempotency_key;
            self::assertSame($substitute ? 123 : ($commissionType === 'percentage' ? 38 : 25), $data->amount_minor);
            self::assertSame('plan-1', $data->plan_id);
            self::assertSame('program-1', $data->program_id);
            self::assertSame('version-1', $data->rule_version_id);

            return true;
        })->andReturn(true, false);
        app()->instance('rewards.repositories.postgresql', $rewards);

        $modules = Mockery::mock(ResolveVolumeRewardModulesPort::class);
        $modules->shouldReceive('execute')->with($moduleId)->twice()->andReturn([new ModuleSummaryData($moduleId, $provider, 'Provider', true, 'running')]);
        $modules->shouldReceive('execute')->withNoArgs()->once()->andReturn([new ModuleSummaryData($moduleId, $provider, 'Provider', true, 'running')]);
        $activities = Mockery::mock(ListVolumeRewardActivitiesPort::class);
        $activities->shouldReceive('execute')->once()->andReturn(new ListVolumeRewardActivitiesResultData($moduleId, 'running', true, [$activity], null, null));
        $limit = Mockery::mock(ResolveVolumeRewardDistributionLimitPort::class);
        $limit->shouldReceive('execute')->times(5)->andReturn(new VolumeRewardDistributionLimitData([$activity->instrument_reference], 0));
        $program = Mockery::mock(ResolveVolumeRewardProgramConfigurationPort::class);
        $program->shouldReceive('execute')->twice()->andReturn(new VolumeRewardProgramConfigurationData(null, 'symbol-config-1', 'both', 'payment-binding-1', 'payment-version-1', $commissionType, '0.25', 0, '0.5', 'USD'));
        $upline = Mockery::mock(ResolveRewardUplinePort::class);
        $upline->shouldReceive('resolve')->once()->andReturn(ResolveRewardUplineResultData::resolved([
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
            $modules,
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

    public function test_periodic_failure_does_not_prevent_the_other_module_from_running(): void
    {
        $processing = Mockery::mock(VolumeRewardProcessingRepositoryInterface::class);
        $processing->shouldReceive('claimNextEvent')->once()->andReturnNull();
        $processing->shouldReceive('claimPeriodicRun')->once()->with('copy-module', null, Mockery::type('object'), Mockery::type('object'))->andThrow(new \RuntimeException('copy unavailable'));
        $processing->shouldReceive('claimPeriodicRun')->once()->with('broker-module', null, Mockery::type('object'), Mockery::type('object'))->andReturnNull();
        app()->instance('rewards.volume-processing.repositories.postgresql', $processing);
        $modules = Mockery::mock(ResolveVolumeRewardModulesPort::class);
        $modules->shouldReceive('execute')->withNoArgs()->once()->andReturn([
            new ModuleSummaryData('copy-module', 'copy_trading', 'Copy Trading', true, 'running'),
            new ModuleSummaryData('broker-module', 'broker', 'Broker', true, 'running'),
        ]);
        $backfill = Mockery::mock(ResolveRewardBackfillStartPort::class);
        $backfill->shouldReceive('execute')->twice()->andReturn(new ResolveRewardBackfillStartData(null));
        $result = $this->useCaseWith($modules, $backfill)->execute(10);
        self::assertSame(0, $result['periodic_pages']);
    }

    private function useCaseWith(ResolveVolumeRewardModulesPort $modules, ?ResolveRewardBackfillStartPort $backfill = null): ProcessVolumeRewardsUseCase
    {
        return new ProcessVolumeRewardsUseCase(
            app(VolumeRewardProcessingRepositoryFactory::class),
            app(RewardRepositoryFactory::class),
            $modules,
            Mockery::mock(ListVolumeRewardActivitiesPort::class),
            Mockery::mock(ResolveVolumeRewardDistributionLimitPort::class),
            Mockery::mock(ResolveVolumeRewardProgramConfigurationPort::class),
            Mockery::mock(ResolveRewardUplinePort::class),
            Mockery::mock(ResolveSubscriptionContextPort::class),
            $backfill ?? Mockery::mock(ResolveRewardBackfillStartPort::class),
            Mockery::mock(ResolveVolumeRewardRuleContextPort::class),
            app(VolumeRewardCalculationStrategyFactory::class),
        );
    }
}
