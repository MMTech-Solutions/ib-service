<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Catalog\Contracts\Repositories\ModuleRepositoryInterface;
use App\Features\Modules\Catalog\Factories\CpaEvidenceProviderFactory;
use App\Features\Modules\Catalog\Models\Module;
use App\Features\Modules\Catalog\Models\ModuleCapability;
use App\Features\Modules\Catalog\UseCases\ListVolumeRewardActivitiesUseCase;
use App\Features\Modules\Catalog\UseCases\ResolveClosedVolumeRewardActivityUseCase;
use App\Features\Modules\Catalog\ValueObjects\ProcessingStatus;
use App\Features\Modules\Contracts\Data\V1\ListCpaEvidenceQueryData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\ResolveClosedVolumeRewardActivityQueryData;
use App\Features\Modules\Contracts\Exceptions\BrokerClosedPositionNotReadyException;
use App\Features\Modules\Contracts\Exceptions\BrokerProgressionActivityUnavailableException;
use App\Features\Modules\Contracts\Exceptions\InvalidProgressionActivityQueryException;
use App\Features\Modules\Contracts\Exceptions\VolumeRewardModuleNotOperationalException;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RewardEvidenceProvidersTest extends TestCase
{
    private function installModule(bool $active = true, string $status = 'running'): void
    {
        $module = new Module('module', 'broker', 'Broker', null, $active, ProcessingStatus::from($status), 1,
            [new ModuleCapability('capability', 'closed_trading_volume', 'Volume', null, true, 'now', 'now')], 'now', 'now');
        $repository = Mockery::mock(ModuleRepositoryInterface::class);
        $repository->shouldReceive('findById')->with('module')->andReturn($module);
        app()->instance('modules.repositories.postgresql', $repository);
        config()->set('broker_catalog.base_url', 'http://broker.test');
        config()->set('finance.base_url', 'http://finance.test');
    }

    /** @return array<string, mixed> */
    private function activity(): array
    {
        return ['source_activity_id' => 'position', 'subject_external_user_id' => 'user', 'metric_code' => 'closed_trading_volume',
            'unit_code' => 'lot', 'quantity' => '1.23456789', 'occurred_at' => '2026-10-01T12:00:00Z',
            'symbol_id' => 'symbol', 'server_group_id' => 'group', 'currency_code' => 'usd', 'currency_precision' => 2,
            'broker_granted_commission' => '3.50'];
    }

    private function pageQuery(): ListVolumeRewardActivitiesQueryData
    {
        return new ListVolumeRewardActivitiesQueryData('module', '2026-10-01T02:00:00+02:00',
            '2026-10-02T02:00:00+02:00', ['broker:server_group:group:symbol:symbol']);
    }

    public function test_volume_channels_preserve_normalization_and_cursor(): void
    {
        $this->installModule();
        Http::fake(fn ($request) => Http::response(['data' => str_contains($request->url(), 'progression-activities') ? [$this->activity()] : $this->activity(), 'meta' => ['next_cursor' => 'next']]));
        $page = app(ListVolumeRewardActivitiesUseCase::class)->execute($this->pageQuery());
        $position = app(ResolveClosedVolumeRewardActivityUseCase::class)->execute(new ResolveClosedVolumeRewardActivityQueryData('module', 'order', 'login'));
        self::assertSame('next', $page->next_cursor);
        self::assertTrue($page->provider_invoked);
        self::assertSame($position->toArray(), $page->activities[0]->toArray());
        self::assertSame('USD', $position->currency_code);
        self::assertSame('1.23456789', $position->quantity);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'progression-activities')
            && $request->data()['from'] === '2026-10-01T00:00:00+00:00'
            && $request->data()['limit'] === (int) config('modules.activity.default_limit', 100));
    }

    /** @return iterable<string, array{bool, string}> */
    public static function conditions(): iterable
    {
        yield 'inactive' => [false, 'running'];
        yield 'paused' => [true, 'paused'];
    }

    #[DataProvider('conditions')]
    public function test_operational_controls_do_not_call_remote_providers(bool $active, string $status): void
    {
        $this->installModule($active, $status);
        Http::fake();
        $page = app(ListVolumeRewardActivitiesUseCase::class)->execute($this->pageQuery());
        self::assertFalse($page->provider_invoked);
        self::assertSame([], $page->activities);
        try {
            app(ResolveClosedVolumeRewardActivityUseCase::class)->execute(new ResolveClosedVolumeRewardActivityQueryData('module', 'order', 'login'));
            self::fail('Expected operational rejection.');
        } catch (VolumeRewardModuleNotOperationalException) {
            Http::assertNothingSent();
        }
    }

    public function test_position_not_ready_propagates(): void
    {
        $this->installModule();
        Http::fake(['*' => Http::response([], 409)]);
        $this->expectException(BrokerClosedPositionNotReadyException::class);
        app(ResolveClosedVolumeRewardActivityUseCase::class)->execute(new ResolveClosedVolumeRewardActivityQueryData('module', 'order', 'login'));
    }

    public function test_page_rejects_invalid_metric(): void
    {
        $this->installModule();
        Http::fake(['*' => Http::response(['data' => [array_replace($this->activity(), ['metric_code' => 'unexpected'])]])]);
        $this->expectException(InvalidProgressionActivityQueryException::class);
        app(ListVolumeRewardActivitiesUseCase::class)->execute($this->pageQuery());
    }

    public function test_cpa_provider_preserves_pagination_and_instrument_filter(): void
    {
        $this->installModule();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'progression-activities')) {
                $second = ($request->data()['cursor'] ?? null) === 'next';

                return Http::response(['data' => [array_replace($this->activity(), ['symbol_id' => $second ? 'other' : 'symbol'])],
                    'meta' => ['next_cursor' => $second ? null : 'next']]);
            }

            return Http::response(['data' => [['id' => 42, 'minor_units' => 2, 'amount_minor' => 101, 'currency_code' => 'USD', 'credited_at' => '2026-10-01T12:00:00Z']], 'meta' => []]);
        });
        $evidence = app(CpaEvidenceProviderFactory::class)->make('broker')->fetch(new ListCpaEvidenceQueryData(
            'module', 'user', '2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z', 'USD', 2,
            [['symbol_reference' => 'broker:server_group:group:symbol:symbol', 'server_group_reference' => 'broker:server_group:group', 'currency_code' => 'USD']]));
        self::assertCount(1, $evidence->volume_facts);
        self::assertSame('finance:ledger:42', $evidence->deposit_facts[0]['source_activity_id']);
        self::assertSame(101, $evidence->deposit_facts[0]['amount_minor']);
        Http::assertSentCount(3);
    }

    public function test_cpa_provider_rejects_deposit_precision_mismatch(): void
    {
        $this->installModule();
        Http::fake(fn ($request) => Http::response(['data' => str_contains($request->url(), 'progression-activities') ? [] :
            [['id' => 42, 'minor_units' => 3, 'amount_minor' => 101, 'currency_code' => 'USD', 'credited_at' => '2026-10-01T12:00:00Z']], 'meta' => []]));
        $this->expectException(InvalidProgressionActivityQueryException::class);
        app(CpaEvidenceProviderFactory::class)->make('broker')->fetch(new ListCpaEvidenceQueryData(
            'module', 'user', '2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z', 'USD', 2, []));
    }

    public function test_cpa_remote_failure_propagates_without_querying_finance(): void
    {
        $this->installModule();
        Http::fake(['*' => Http::response([], 503)]);
        try {
            app(CpaEvidenceProviderFactory::class)->make('broker')->fetch(new ListCpaEvidenceQueryData(
                'module', 'user', '2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z', 'USD', 2, []));
            self::fail('Expected provider failure.');
        } catch (BrokerProgressionActivityUnavailableException) {
            Http::assertSentCount(1);
        }
    }
}
