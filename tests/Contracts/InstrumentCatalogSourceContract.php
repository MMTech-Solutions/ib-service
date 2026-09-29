<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Features\Modules\Contracts\Data\V1\ListInstrumentCatalogQueryData;
use App\Features\Modules\Sources\Contracts\InstrumentCatalogSourceInterface;
use Tests\TestCase;

abstract class InstrumentCatalogSourceContract extends TestCase
{
    abstract protected function source(): InstrumentCatalogSourceInterface;

    public function test_it_lists_normalized_hierarchy_with_opaque_references(): void
    {
        $result = $this->source()->list($this->catalogQuery(type: 'symbol'));

        self::assertSame(2, $result->total);
        self::assertSame('broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005', $result->items[0]->reference);
        self::assertSame('symbol', $result->items[0]->type);
        self::assertSame('broker:server_group:00000000-0000-7000-8000-000000000003', $result->items[0]->parents['server_group']);
        self::assertSame('USD', $result->items[0]->currency_code);
    }

    public function test_it_lists_each_supported_catalog_type(): void
    {
        $expectedReferences = [
            'platform' => 'broker:platform:00000000-0000-7000-8000-000000000001',
            'trading_server' => 'broker:trading_server:00000000-0000-7000-8000-000000000002',
            'server_group' => 'broker:server_group:00000000-0000-7000-8000-000000000003',
            'security' => 'broker:security:00000000-0000-7000-8000-000000000004',
            'symbol' => 'broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005',
        ];

        foreach ($expectedReferences as $type => $reference) {
            $result = $this->source()->list($this->catalogQuery(type: $type));

            self::assertSame($reference, $result->items[0]->reference);
            self::assertSame($type, $result->items[0]->type);
        }
    }

    public function test_it_filters_by_hierarchy_and_search_without_exposing_provider_identifiers(): void
    {
        $result = $this->source()->list($this->catalogQuery(
            type: 'symbol',
            filters: [
                'server_group' => 'broker:server_group:00000000-0000-7000-8000-000000000003',
                'search' => 'eur',
            ],
        ));

        self::assertSame(1, $result->total);
        self::assertSame('broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000005', $result->items[0]->reference);
    }

    public function test_it_paginates_catalog_results_and_preserves_page_metadata(): void
    {
        $result = $this->source()->list($this->catalogQuery(type: 'symbol', page: 2, perPage: 1));

        self::assertSame(2, $result->total);
        self::assertSame(2, $result->page);
        self::assertSame(1, $result->per_page);
        self::assertCount(1, $result->items);
        self::assertSame('broker:server_group:00000000-0000-7000-8000-000000000003:symbol:00000000-0000-7000-8000-000000000006', $result->items[0]->reference);
    }

    /** @param array<string, string> $filters */
    protected function catalogQuery(
        string $type,
        array $filters = [],
        int $page = 1,
        int $perPage = 100,
    ): ListInstrumentCatalogQueryData {
        return new ListInstrumentCatalogQueryData(
            module_id: '01993ac2-8750-73fd-b102-ba24fb06d8be',
            type: $type,
            filters: $filters,
            page: $page,
            per_page: $perPage,
        );
    }
}
