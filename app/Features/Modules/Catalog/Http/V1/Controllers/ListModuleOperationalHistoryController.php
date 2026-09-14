<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\DTOs\ModuleOperationalChangeData;
use App\Features\Modules\Catalog\Http\V1\Commands\ListModuleOperationalHistoryCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\ListModuleOperationalHistoryRequest;
use App\Features\Modules\Catalog\UseCases\ListModuleOperationalHistoryUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListModuleOperationalHistoryController
{
    use ApiResponse;

    public function __invoke(
        ListModuleOperationalHistoryRequest $request,
        ListModuleOperationalHistoryUseCase $useCase,
    ): JsonResponse {
        $command = ListModuleOperationalHistoryCommand::fromRequest($request);
        $result = $useCase->execute($command);
        $entries = array_map(
            static fn (ModuleOperationalChangeData $entry): array => $entry->toArray(),
            $result->entries,
        );
        $paginator = new LengthAwarePaginator(
            items: $entries,
            total: $result->total,
            perPage: $result->perPage,
            currentPage: $result->currentPage,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );

        return $this->success(
            data: $entries,
            message: 'Module operational history retrieved successfully.',
            paginator: $paginator,
        );
    }
}
