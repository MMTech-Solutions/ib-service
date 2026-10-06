<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaVolumeEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Rewards\Contracts\Events\V1\CpaContextExpired;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\Support\CpaFixtures;
use Tests\TestCase;

final class VerifyCpaContextsUseCaseTest extends TestCase
{
    use CpaFixtures;

    public function test_incremental_query_and_source_failure_do_not_block_other_modules(): void
    {
        $this->travelTo(now('UTC')->startOfSecond());
        $configuration = $this->cpaConfiguration(['a', 'b']);
        $context = (object) [
            'id' => 'context',
            'referred_user_id' => 'user',
            'ib_user_id' => 'ib',
            'plan_id' => 'plan',
            'program_id' => 'program',
            'rule_id' => 'rule',
            'rule_version_id' => 'version',
            'captured_at' => now()->subDays(2)->toISOString(),
            'requirements_snapshot' => json_encode($configuration),
        ];
        $sources = [
            (object) ['id' => 'a', 'module_id' => 'a', 'kind' => 'volume', 'symbols_snapshot' => '[{"symbol_reference":"symbol"}]', 'observed_until' => now()->subDay()->toISOString()],
            (object) ['id' => 'b', 'module_id' => 'b', 'kind' => 'volume', 'symbols_snapshot' => '[]', 'observed_until' => null],
        ];
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('listCpaContextsWithoutReward')->once()->with(10)->andReturn([$context]);
        $repository->shouldReceive('listCpaSources')->once()->with('context')->andReturn($sources);
        $repository->shouldReceive('persistCpaSource')->once()->with($context, $sources[0], Mockery::on(fn ($entries): bool => count($entries) === 1 && $entries[0]->points === '40.0000000000000000'), Mockery::type('object'));
        $repository->shouldReceive('markCpaSource')->once()->with('b', 'error', 'evidence_unavailable', Mockery::type('object'));
        $repository->shouldReceive('completeCpaVerification')->once()->andReturn('qualified');
        app()->instance('rewards.repositories.postgresql', $repository);
        $modules = Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->andReturnUsing(fn ($ids): array => [new ModuleSummaryData($ids[0], 'broker', 'Broker', true, 'running')]);
        app()->instance(ResolveModulesPort::class, $modules);
        $provider = Mockery::mock(ListCpaEvidencePort::class);
        $provider->shouldReceive('list')->once()->with(Mockery::on(fn ($q): bool => $q->module_id === 'a' && $q->occurred_from === CarbonImmutable::parse($sources[0]->observed_until)->toIso8601String()))->andReturn(new CpaEvidenceData([new CpaVolumeEvidenceData('p', 'user', 'closed_trading_volume', 'lot', '2', now()->subHour()->toISOString(), 'symbol')], []));
        $provider->shouldReceive('list')->once()->with(Mockery::on(fn ($q): bool => $q->module_id === 'b'))->andThrow(new \RuntimeException('unavailable'));
        app()->instance(ListCpaEvidencePort::class, $provider);
        app()->forgetInstance(VerifyCpaContextsUseCase::class);
        self::assertSame(1, app(VerifyCpaContextsUseCase::class)->execute(10)['qualified']);
    }

    public function test_expires_context_before_verification_when_waiting_period_exceeded(): void
    {
        Event::fake([CpaContextExpired::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-06T12:00:00Z'));
        $configuration = $this->cpaConfiguration(['a']);
        $configuration['expiration_days'] = 1;
        $context = (object) [
            'id' => 'context',
            'referred_user_id' => 'user',
            'ib_user_id' => 'ib',
            'plan_id' => 'plan',
            'program_id' => 'program',
            'rule_id' => 'rule',
            'rule_version_id' => 'version',
            'captured_at' => '2026-10-04T08:00:00Z',
            'requirements_snapshot' => json_encode($configuration),
        ];
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('listCpaContextsWithoutReward')->once()->andReturn([$context]);
        $repository->shouldReceive('expireCpaContext')->once()->with($context, VerifyCpaContextsUseCase::EXPIRATION_REASON, Mockery::type(CarbonImmutable::class))->andReturn(true);
        $repository->shouldNotReceive('listCpaSources');
        $repository->shouldNotReceive('completeCpaVerification');
        app()->instance('rewards.repositories.postgresql', $repository);
        app()->forgetInstance(VerifyCpaContextsUseCase::class);

        $result = app(VerifyCpaContextsUseCase::class)->execute(10);

        self::assertSame(1, $result['expired']);
        self::assertSame(0, $result['qualified']);
        Event::assertDispatched(CpaContextExpired::class, function (CpaContextExpired $event) use ($context): bool {
            return $event->cpaContextId === $context->id
                && $event->expirationDays === 1
                && $event->daysElapsed === 2
                && $event->reason === VerifyCpaContextsUseCase::EXPIRATION_REASON;
        });
    }

    public function test_does_not_expire_on_exact_expiration_day_threshold(): void
    {
        Event::fake([CpaContextExpired::class]);
        $this->travelTo(CarbonImmutable::parse('2026-10-05T12:00:00Z'));
        $configuration = $this->cpaConfiguration(['a']);
        $configuration['expiration_days'] = 1;
        $context = (object) [
            'id' => 'context',
            'referred_user_id' => 'user',
            'ib_user_id' => 'ib',
            'plan_id' => 'plan',
            'program_id' => 'program',
            'rule_id' => 'rule',
            'rule_version_id' => 'version',
            'captured_at' => '2026-10-04T08:00:00Z',
            'requirements_snapshot' => json_encode($configuration),
        ];
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('listCpaContextsWithoutReward')->once()->andReturn([$context]);
        $repository->shouldReceive('listCpaSources')->once()->andReturn([]);
        $repository->shouldReceive('completeCpaVerification')->once()->andReturn('pending');
        $repository->shouldNotReceive('expireCpaContext');
        app()->instance('rewards.repositories.postgresql', $repository);
        app()->forgetInstance(VerifyCpaContextsUseCase::class);

        self::assertSame(1, app(VerifyCpaContextsUseCase::class)->execute(10)['evaluated']);
        Event::assertNotDispatched(CpaContextExpired::class);
    }
}
