<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Contracts\Data\V1\ListVolumeRewardActivitiesQueryData;
use App\Features\Modules\Contracts\Data\V1\VolumeRewardEventQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Contracts\Ports\Input\ListInstrumentCatalogPort;
use App\Features\Modules\Contracts\Ports\Input\ListVolumeRewardActivitiesPort;
use App\Features\Modules\Contracts\Ports\Input\NormalizeVolumeRewardEventPort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\VolumeRewardEventFixtures;
use Tests\TestCase;

final class CopyTradingVolumeProviderTest extends TestCase
{
    use RefreshDatabase, VolumeRewardEventFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('modules.sources.copy_trading.base_url', 'http://copy.test');
        config()->set('modules.sources.copy_trading.topic', 'copy.events');
        config()->set('modules.sources.copy_trading.internal_token', 'test-token');
        $this->artisan('modules:sync')->assertSuccessful();
    }

    public function test_copy_trading_feed_and_event_have_identical_normalized_evidence(): void
    {
        $moduleId = DB::table('modules')->where('code', 'copy_trading')->value('id');
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [$this->activity('copy_trading')], 'meta' => ['next_cursor' => 'next']])]);
        $query = new ListVolumeRewardActivitiesQueryData($moduleId, '2026-10-06T00:00:00Z', '2026-10-07T00:00:00Z', ['copy_trading:server_group:group:symbol:symbol']);
        $page = app(ListVolumeRewardActivitiesPort::class)->execute($query);
        $event = app(NormalizeVolumeRewardEventPort::class)->execute(new VolumeRewardEventQueryData('copy.events', 'position_closed', $this->body('copy_trading')));
        self::assertSame($event->activity->toArray(), $page->activities[0]->toArray());
        self::assertSame('next', $page->next_cursor);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Internal-Token', 'test-token') && $request->hasHeader('X-Internal-Source', 'mmt-ib-service') && str_contains($request->url(), '/api/copy-trading/v1/internal/progression-activities'));
    }

    public function test_catalog_exposes_copy_trading_namespaces_and_maps_filters(): void
    {
        $moduleId = DB::table('modules')->where('code', 'copy_trading')->value('id');
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [
            ['type' => 'symbol', 'reference' => 'symbol', 'name' => 'EURUSD', 'parents' => ['server_group' => 'group'], 'currency_code' => 'USD'],
        ], 'meta' => ['pagination' => ['total' => 1, 'current_page' => 1, 'per_page' => 10]]])]);
        $page = app(ListInstrumentCatalogPort::class)->execute(new ListInstrumentCatalogQueryData($moduleId, 'symbol', [
            'server_group' => 'copy_trading:server_group:group', 'symbol' => 'copy_trading:server_group:group:symbol:symbol',
        ], 1, 10));
        self::assertSame('copy_trading:server_group:group:symbol:symbol', $page->items[0]->reference);
        self::assertSame('copy_trading:server_group:group', $page->items[0]->parents['server_group']);
        Http::assertSent(fn ($request) => $request->data()['server_group_reference'] === 'group' && $request->data()['symbol_reference'] === 'symbol');
    }

    public function test_incomplete_feed_does_not_confirm_the_page(): void
    {
        $moduleId = DB::table('modules')->where('code', 'copy_trading')->value('id');
        Http::fake(['copy.test/*' => Http::response(['success' => true, 'data' => [$this->activity('copy_trading')], 'meta' => []])]);
        $this->expectException(InvalidVolumeRewardActivityException::class);
        app(ListVolumeRewardActivitiesPort::class)->execute(new ListVolumeRewardActivitiesQueryData($moduleId, '2026-10-06T00:00:00Z', '2026-10-07T00:00:00Z', ['copy_trading:server_group:group:symbol:symbol']));
    }
}
