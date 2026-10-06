<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Features\Modules\Contracts\Data\V1\CpaEvidenceData;
use App\Features\Modules\Contracts\Data\V1\CpaVolumeEvidenceData;
use App\Features\Modules\Contracts\Data\V1\ModuleSummaryData;
use App\Features\Modules\Contracts\Ports\Input\ListCpaEvidencePort;
use App\Features\Modules\Contracts\Ports\Input\ResolveModulesPort;
use App\Features\Rewards\Repositories\RewardRepositoryInterface;
use App\Features\Rewards\UseCases\VerifyCpaContextsUseCase;
use Carbon\CarbonImmutable;
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
        $context = (object) ['id' => 'context', 'referred_user_id' => 'user', 'captured_at' => now()->subDays(2)->toISOString(), 'requirements_snapshot' => json_encode($configuration)];
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
}
