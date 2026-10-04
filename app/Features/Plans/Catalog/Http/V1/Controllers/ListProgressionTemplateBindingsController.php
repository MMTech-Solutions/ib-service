<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\DTOs\ProgressionTemplateBindingData;
use App\Features\Plans\Catalog\Http\V1\Commands\ListProgressionTemplateBindingsCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ListProgressionTemplateBindingsRequest;
use App\Features\Plans\Catalog\UseCases\ListProgressionTemplateBindingsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListProgressionTemplateBindingsController
{
    use ApiResponse;

    public function __invoke(ListProgressionTemplateBindingsRequest $request, ListProgressionTemplateBindingsUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ListProgressionTemplateBindingsCommand::fromRequest($request));
        $items = array_map(static fn (ProgressionTemplateBindingData $binding): array => $binding->toArray(), $result->items);
        $paginator = new LengthAwarePaginator($items, $result->total, $result->perPage, $result->currentPage, ['path' => $request->url(), 'pageName' => 'page']);

        return $this->success(data: $items, message: 'Template bindings retrieved successfully.', paginator: $paginator);
    }
}
