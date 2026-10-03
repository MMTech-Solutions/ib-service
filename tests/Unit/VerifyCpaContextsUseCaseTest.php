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
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class VerifyCpaContextsUseCaseTest extends TestCase
{
    /** @return iterable<string, array{bool, bool, bool}> */
    public static function modes(): iterable
    {
        yield 'complete qualified' => [false, true, true];
        yield 'complete pending' => [false, false, false];
        yield 'incremental pending' => [true, false, false];
        yield 'incremental qualified confirmed' => [true, true, true];
        yield 'incremental qualified rejected by full evidence' => [true, true, false];
    }

    #[DataProvider('modes')]
    public function test_modes_preserve_queries_and_persistence(bool $incremental, bool $firstQualifies, bool $fullQualifies): void
    {
        config()->set('rewards.cpa.incremental_evidence', $incremental);
        $context = $this->context();
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('listCpaContextsWithoutReward')->once()->with(10)->andReturn([$context]);
        app()->instance('rewards.repositories.postgresql', $repository);
        $modules = Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->once()->with(['module'])->andReturn([new ModuleSummaryData('module', 'broker', 'Broker', true, 'running')]);
        app()->instance(ResolveModulesPort::class, $modules);
        $evidence = Mockery::mock(ListCpaEvidencePort::class);
        $evidence->shouldReceive('list')->once()->with(Mockery::on(fn ($query): bool => $query->occurred_from === ($incremental ? '2026-10-02T00:00:00+00:00' : '2026-10-01T00:00:00+00:00')))->andReturn($this->evidence($firstQualifies ? '2' : '0', $firstQualifies ? 100 : 0));
        $recheck = $incremental && $firstQualifies;
        if ($recheck) {
            $evidence->shouldReceive('list')->once()->with(Mockery::on(fn ($query): bool => $query->occurred_from === '2026-10-01T00:00:00+00:00'))->andReturn($this->evidence($fullQualifies ? '2' : '0', $fullQualifies ? 100 : 0));
        }
        app()->instance(ListCpaEvidencePort::class, $evidence);
        if ($firstQualifies) {
            $repository->shouldReceive('persistQualifiedCpaContext')->once()->with($context, Mockery::type('array'), Mockery::type(CpaEvidenceData::class), $fullQualifies ? '2.00000000' : '0.00000000', $fullQualifies ? 100 : 0, Mockery::type('object'), $fullQualifies);
        } else {
            $repository->shouldReceive('updateCpaProgress')->once()->with('context', $incremental ? '0.50000000' : '0.00000000', $incremental ? 25 : 0, Mockery::type('object'), 'pending', null, Mockery::type('array'));
        }
        $result = app(VerifyCpaContextsUseCase::class)->execute(10);
        self::assertSame($fullQualifies ? 1 : 0, $result['qualified']);
        self::assertSame($fullQualifies ? 0 : 1, $result['evaluated']);
    }

    /** @return iterable<string, array{bool, string}> */
    public static function conditions(): iterable
    {
        yield 'inactive' => [false, 'running'];
        yield 'paused' => [true, 'paused'];
    }

    #[DataProvider('conditions')]
    public function test_non_operational_module_skips_evidence(bool $active, string $status): void
    {
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('listCpaContextsWithoutReward')->once()->andReturn([$this->context()]);
        app()->instance('rewards.repositories.postgresql', $repository);
        $modules = Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->once()->andReturn([new ModuleSummaryData('module', 'broker', 'Broker', $active, $status)]);
        app()->instance(ResolveModulesPort::class, $modules);
        app()->instance(ListCpaEvidencePort::class, Mockery::mock(ListCpaEvidencePort::class));
        self::assertSame(1, app(VerifyCpaContextsUseCase::class)->execute(10)['skipped']);
    }

    public function test_provider_failure_preserves_observed_values(): void
    {
        $repository = Mockery::mock(RewardRepositoryInterface::class);
        $repository->shouldReceive('listCpaContextsWithoutReward')->once()->andReturn([$this->context()]);
        $repository->shouldReceive('updateCpaProgress')->once()->with('context', '0.5', 25, Mockery::type('object'), 'error', 'evidence_unavailable');
        app()->instance('rewards.repositories.postgresql', $repository);
        $modules = Mockery::mock(ResolveModulesPort::class);
        $modules->shouldReceive('findByIds')->once()->andReturn([new ModuleSummaryData('module', 'broker', 'Broker', true, 'running')]);
        app()->instance(ResolveModulesPort::class, $modules);
        $evidence = Mockery::mock(ListCpaEvidencePort::class);
        $evidence->shouldReceive('list')->once()->andThrow(new \RuntimeException('provider failed'));
        app()->instance(ListCpaEvidencePort::class, $evidence);
        self::assertSame(1, app(VerifyCpaContextsUseCase::class)->execute(10)['errored']);
    }

    private function context(): object
    {
        return (object) ['id' => 'context', 'module_id' => 'module', 'referred_user_id' => 'referred', 'captured_at' => '2026-10-01T00:00:00Z', 'observed_until' => '2026-10-02T00:00:00Z', 'observed_volume' => '0.5', 'observed_deposit_minor' => 25, 'requirements_snapshot' => json_encode(['required_volume' => '2', 'required_deposit_minor' => 100, 'currency_code' => 'USD', 'currency_precision' => 2], JSON_THROW_ON_ERROR), 'symbols_snapshot' => '[]'];
    }

    private function evidence(string $quantity, int $deposit): CpaEvidenceData
    {
        return new CpaEvidenceData([new CpaVolumeEvidenceData('position', 'referred', 'volume', 'lot', $quantity, '2026-10-02T01:00:00Z', 'symbol')], [['amount_minor' => $deposit]]);
    }
}
