<?php

declare(strict_types=1);

namespace App\Features\Plans\Catalog\Http\V1\Controllers;

use App\Features\Plans\Catalog\DTOs\PaymentTemplateBindingData;
use App\Features\Plans\Catalog\Http\V1\Commands\ListPaymentTemplateBindingsCommand;
use App\Features\Plans\Catalog\Http\V1\Requests\ListPaymentTemplateBindingsRequest;
use App\Features\Plans\Catalog\UseCases\ListPaymentTemplateBindingsUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListPaymentTemplateBindingsController
{
    use ApiResponse;

    public function __invoke(ListPaymentTemplateBindingsRequest $request, ListPaymentTemplateBindingsUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute(ListPaymentTemplateBindingsCommand::fromRequest($request));
        $items = array_map(static fn (PaymentTemplateBindingData $binding): array => $binding->toArray(), $result->items);
        $paginator = new LengthAwarePaginator($items, $result->total, $result->perPage, $result->currentPage, ['path' => $request->url(), 'pageName' => 'page']);

        return $this->success(data: $items, message: 'Template bindings retrieved successfully.', paginator: $paginator);
    }
}
