<?php

declare(strict_types=1);

namespace App\Features\Modules\Catalog\Http\V1\Controllers;

use App\Features\Modules\Catalog\Contracts\Data\ModuleData;
use App\Features\Modules\Catalog\Http\V1\Commands\ListModulesCommand;
use App\Features\Modules\Catalog\Http\V1\Requests\ListModulesRequest;
use App\Features\Modules\Catalog\UseCases\ListModulesUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListModulesController
{
    use ApiResponse;

    public function __invoke(ListModulesRequest $request, ListModulesUseCase $useCase): JsonResponse
    {
        $command = ListModulesCommand::fromRequest($request);
        $result = $useCase->execute($command->toQueryData());
        $modules = array_map(static fn (ModuleData $module): array => $module->toArray(), $result->modules);
        $paginator = new LengthAwarePaginator(
            items: $modules,
            total: $result->total,
            perPage: $result->perPage,
            currentPage: $result->currentPage,
            options: ['path' => $request->url(), 'pageName' => 'page'],
        );
        $filters = array_filter(
            $request->safe()->only(['search', 'is_active', 'processing_status']),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return $this->success(
            data: ['modules' => $modules],
            message: 'Modules retrieved successfully.',
            meta: ['filters' => (object) $filters],
            paginator: $paginator,
        );
    }
}
