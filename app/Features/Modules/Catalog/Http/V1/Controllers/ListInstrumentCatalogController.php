<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Http\V1\Commands\ListInstrumentCatalogCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\ListInstrumentCatalogRequest;
use App\Features\Modules\Contracts\Data\V1\InstrumentCatalogItemData;
use App\Features\Modules\Contracts\Ports\Input\ListInstrumentCatalogPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListInstrumentCatalogController
{
    use ApiResponse;

    public function __invoke(ListInstrumentCatalogRequest $request, ListInstrumentCatalogPort $useCase): JsonResponse
    {
        $command = ListInstrumentCatalogCommand::fromRequest($request);
        $result = $useCase->execute($command->toQueryData());
        $items = array_map(static fn (InstrumentCatalogItemData $item): array => $item->toArray(), $result->items);
        $paginator = new LengthAwarePaginator($items, $result->total, $result->per_page, $result->page, ['path' => $request->url()]);

        return $this->success(
            data: $items,
            message: 'Instrument catalogue retrieved successfully.',
            meta: ['filters' => (object) $command->filters],
            paginator: $paginator,
        );
    }
}
