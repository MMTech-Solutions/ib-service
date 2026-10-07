<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\CopyTrading\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogItemData;
use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Contracts\Exceptions\InvalidVolumeRewardActivityException;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;
use App\Features\Modules\Sources\CopyTrading\Services\CopyTradingApiClient;

final class CopyTradingInstrumentCatalogAdapter implements InstrumentCatalogSourceInterface
{
    public function __construct(private readonly CopyTradingApiClient $client) {}

    public function list(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData
    {
        $response = $this->client->get('/instrument-catalog/'.$query->type, $this->query($query));
        $pagination = $response['meta']['pagination'] ?? [];
        foreach (['total', 'current_page', 'per_page'] as $field) {
            if (! is_int($pagination[$field] ?? null) || $pagination[$field] < ($field === 'total' ? 0 : 1)) {
                throw InvalidVolumeRewardActivityException::create();
            }
        }

        return new InstrumentCatalogPageData(array_map(fn (array $item): InstrumentCatalogItemData => $this->item($item), $response['data']), (int) ($pagination['total'] ?? count($response['data'])), (int) ($pagination['current_page'] ?? $query->page), (int) ($pagination['per_page'] ?? $query->per_page));
    }

    private function query(ListInstrumentCatalogQueryData $query): array
    {
        $result = ['page' => $query->page, 'per_page' => $query->per_page];
        foreach ($query->filters as $key => $value) {
            $mapped = $key === 'search' ? 'search' : $key.'_reference';
            $result[$mapped] = $key === 'search' ? $value : ($key === 'symbol' ? $this->symbolId($value) : $this->id($value, $key));
        }

        return $result;
    }

    private function item(array $item): InstrumentCatalogItemData
    {
        foreach (['type', 'reference', 'name'] as $field) {
            if (! is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                throw InvalidVolumeRewardActivityException::create();
            }
        }
        if (! in_array($item['type'], ['platform', 'trading_server', 'server_group', 'security', 'symbol'], true) || ! is_array($item['parents'] ?? null) || ($item['type'] === 'symbol' && ! is_string($item['parents']['server_group'] ?? null))) {
            throw InvalidVolumeRewardActivityException::create();
        }
        $type = (string) $item['type'];
        if (str_contains($item['reference'], ':')) {
            throw InvalidVolumeRewardActivityException::create();
        }
        $parents = [];
        foreach ((array) ($item['parents'] ?? []) as $key => $value) {
            if (! in_array($key, ['platform', 'trading_server', 'server_group', 'security'], true) || ! is_string($value) || trim($value) === '' || str_contains($value, ':')) {
                throw InvalidVolumeRewardActivityException::create();
            }
            $parents[$key] = $this->reference($key, $value);
        }
        $reference = $type === 'symbol' ? $this->symbolReference((string) ($item['parents']['server_group'] ?? ''), (string) $item['reference']) : $this->reference($type, (string) $item['reference']);

        return new InstrumentCatalogItemData($reference, $type, (string) $item['name'], $parents, isset($item['currency_code']) ? (string) $item['currency_code'] : null);
    }

    private function reference(string $type, string $id): string
    {
        return 'copy_trading:'.$type.':'.$id;
    }

    private function symbolReference(string $groupId, string $symbolId): string
    {
        return 'copy_trading:server_group:'.$groupId.':symbol:'.$symbolId;
    }

    private function id(string $reference, string $type): string
    {
        $prefix = 'copy_trading:'.$type.':';
        if (! str_starts_with($reference, $prefix)) {
            return '__invalid_reference__';
        }

        return substr($reference, strlen($prefix));
    }

    private function symbolId(string $reference): string
    {
        $parts = explode(':', $reference);

        return count($parts) === 5 && $parts[0] === 'copy_trading' && $parts[1] === 'server_group' && $parts[3] === 'symbol' ? $parts[4] : '__invalid_reference__';
    }
}
