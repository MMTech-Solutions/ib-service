<?php

declare(strict_types=1);

namespace App\Features\Modules\Sources\Broker\Services\Adapters;

use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogItemData;
use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogPageData;
use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Sources\Broker\Services\BrokerInstrumentCatalogApiClient;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;

final class BrokerInstrumentCatalogAdapter implements InstrumentCatalogSourceInterface
{
    public function __construct(private readonly BrokerInstrumentCatalogApiClient $client) {}

    public function list(ListInstrumentCatalogQueryData $query): InstrumentCatalogPageData
    {
        $response = $this->client->list($query->type, $this->query($query));
        $pagination = $response['meta']['pagination'] ?? [];

        return new InstrumentCatalogPageData(array_map(fn (array $item): InstrumentCatalogItemData => $this->item($item), $response['data']), (int) ($pagination['total'] ?? count($response['data'])), (int) ($pagination['current_page'] ?? $query->page), (int) ($pagination['per_page'] ?? $query->per_page));
    }

    private function query(ListInstrumentCatalogQueryData $query): array
    {
        $result = ['page' => $query->page, 'per_page' => $query->per_page];
        foreach ($query->filters as $key => $value) {
            $mapped = $key === 'search' ? 'search' : $key.'_reference';
            $result[$mapped] = $key === 'symbol' ? $this->symbolId($value) : $this->id($value, $key);
        }

        return $result;
    }

    private function item(array $item): InstrumentCatalogItemData
    {
        $type = (string) $item['type'];
        $parents = [];
        foreach ((array) ($item['parents'] ?? []) as $key => $value) {
            $parents[$key] = $this->reference($key, (string) $value, $key === 'symbol' ? null : null);
        }
        $reference = $type === 'symbol' ? $this->symbolReference((string) ($item['parents']['server_group'] ?? ''), (string) $item['reference']) : $this->reference($type, (string) $item['reference']);

        return new InstrumentCatalogItemData($reference, $type, (string) $item['name'], $parents, isset($item['currency_code']) ? (string) $item['currency_code'] : null);
    }

    private function reference(string $type, string $id, ?string $unused = null): string
    {
        return 'broker:'.$type.':'.$id;
    }

    private function symbolReference(string $groupId, string $symbolId): string
    {
        return 'broker:server_group:'.$groupId.':symbol:'.$symbolId;
    }

    private function id(string $reference, string $type): string
    {
        $prefix = 'broker:'.$type.':';
        if (! str_starts_with($reference, $prefix)) {
            return '__invalid_reference__';
        }

        return substr($reference, strlen($prefix));
    }

    private function symbolId(string $reference): string
    {
        $parts = explode(':', $reference);

        return count($parts) === 5 && $parts[0] === 'broker' && $parts[1] === 'server_group' && $parts[3] === 'symbol' ? $parts[4] : '__invalid_reference__';
    }
}
