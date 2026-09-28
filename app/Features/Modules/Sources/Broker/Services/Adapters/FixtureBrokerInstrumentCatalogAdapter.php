<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogItemData;
use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;

/** Temporary Broker catalogue. Its references are Module contracts, not Broker IDs. */
final class FixtureBrokerInstrumentCatalogAdapter implements InstrumentCatalogSourceInterface
{
    public function list(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData
    {
        $items = array_values(array_filter(
            $this->items(),
            function (InstrumentCatalogItemData $item) use ($query): bool {
                if ($item->type !== $query->type) {
                    return false;
                }

                foreach ($query->filters as $key => $value) {
                    if ($key === 'search') {
                        if (! str_contains(mb_strtolower($item->name), mb_strtolower($value))) {
                            return false;
                        }

                        continue;
                    }
                    if (($item->parents[$key] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            },
        ));

        $offset = ($query->page - 1) * $query->per_page;

        return new InstrumentCatalogPageData(
            items: array_slice($items, $offset, $query->per_page),
            total: count($items),
            page: $query->page,
            per_page: $query->per_page,
        );
    }

    /** @return list<InstrumentCatalogItemData> */
    private function items(): array
    {
        return [
            new InstrumentCatalogItemData('broker:platform:mt5', 'platform', 'MetaTrader 5', []),
            new InstrumentCatalogItemData('broker:server:mt5-live', 'trading_server', 'MT5 Live', ['platform' => 'broker:platform:mt5']),
            new InstrumentCatalogItemData('broker:group:mt5-usd', 'server_group', 'MT5 USD', ['platform' => 'broker:platform:mt5', 'trading_server' => 'broker:server:mt5-live'], 'USD'),
            new InstrumentCatalogItemData('broker:security:fx', 'security', 'Forex', ['platform' => 'broker:platform:mt5', 'trading_server' => 'broker:server:mt5-live', 'server_group' => 'broker:group:mt5-usd']),
            new InstrumentCatalogItemData('broker:symbol:eurusd', 'symbol', 'EURUSD', ['platform' => 'broker:platform:mt5', 'trading_server' => 'broker:server:mt5-live', 'server_group' => 'broker:group:mt5-usd', 'security' => 'broker:security:fx'], 'USD'),
            new InstrumentCatalogItemData('broker:symbol:gbpusd', 'symbol', 'GBPUSD', ['platform' => 'broker:platform:mt5', 'trading_server' => 'broker:server:mt5-live', 'server_group' => 'broker:group:mt5-usd', 'security' => 'broker:security:fx'], 'USD'),
        ];
    }
}
