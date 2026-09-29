<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalog;

use App\Features\Modules\Sources\Broker\Services\Adapters\BrokerInstrumentCatalogAdapter;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;
use Illuminate\Support\Facades\Http;
use Tests\Contracts\InstrumentCatalogSourceContract;

final class FixtureBrokerInstrumentCatalogAdapterContractTest extends InstrumentCatalogSourceContract
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function ($request) {
            $type = basename(parse_url($request->url(), PHP_URL_PATH));
            $items = $this->items();
            $item = match ($type) {
                'platform' => ['reference' => '00000000-0000-7000-8000-000000000001', 'type' => 'platform', 'name' => 'MetaTrader 5', 'parents' => []],
                'trading_server' => ['reference' => '00000000-0000-7000-8000-000000000002', 'type' => 'trading_server', 'name' => 'MT5 Live', 'parents' => ['platform' => '00000000-0000-7000-8000-000000000001']],
                'server_group' => ['reference' => '00000000-0000-7000-8000-000000000003', 'type' => 'server_group', 'name' => 'MT5 USD', 'parents' => ['platform' => '00000000-0000-7000-8000-000000000001', 'trading_server' => '00000000-0000-7000-8000-000000000002']],
                'security' => ['reference' => '00000000-0000-7000-8000-000000000004', 'type' => 'security', 'name' => 'Forex', 'parents' => ['server_group' => '00000000-0000-7000-8000-000000000003']],
                default => $items[(int) ($request->data()['page'] ?? 1) - 1] ?? $items[0],
            };

            $filtered = isset($request->data()['search']);

            return Http::response(['data' => [$item], 'meta' => ['pagination' => ['total' => $type === 'symbol' && ! $filtered ? 2 : 1, 'current_page' => (int) ($request->data()['page'] ?? 1), 'per_page' => (int) ($request->data()['per_page'] ?? 100)]]]);
        });
    }

    protected function source(): InstrumentCatalogSourceInterface
    {
        return resolve(BrokerInstrumentCatalogAdapter::class);
    }

    private function items(): array
    {
        return [['reference' => '00000000-0000-7000-8000-000000000005', 'type' => 'symbol', 'name' => 'EURUSD', 'parents' => ['platform' => '00000000-0000-7000-8000-000000000001', 'trading_server' => '00000000-0000-7000-8000-000000000002', 'server_group' => '00000000-0000-7000-8000-000000000003'], 'currency_code' => 'USD'], ['reference' => '00000000-0000-7000-8000-000000000006', 'type' => 'symbol', 'name' => 'GBPUSD', 'parents' => ['server_group' => '00000000-0000-7000-8000-000000000003'], 'currency_code' => 'USD']];
    }
}
