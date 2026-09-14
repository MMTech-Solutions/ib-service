<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\ReorderProgramsCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\ReorderProgramsRequest;
use App\Features\Programs\Catalog\UseCases\ReorderProgramsUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ReorderProgramsController
{
    use ApiResponse;

    public function __invoke(ReorderProgramsRequest $request, ReorderProgramsUseCase $useCase): JsonResponse
    {
        $page = $useCase->execute(ReorderProgramsCommand::fromRequest($request));

        return $this->success(
            array_map(
                static fn ($program): array => $program->toArray(),
                $page->programs,
            ),
            'Programs reordered successfully.',
        );
    }
}
