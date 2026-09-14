<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\ListProgramsCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\ListProgramsRequest;
use App\Features\Programs\Catalog\UseCases\ListProgramsUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListProgramsController
{
    use ApiResponse;

    public function __invoke(ListProgramsRequest $request, ListProgramsUseCase $useCase): JsonResponse
    {
        $page = $useCase->execute(ListProgramsCommand::fromRequest($request));

        return $this->success(
            array_map(
                static fn ($program): array => $program->toArray(),
                $page->programs,
            ),
            'Programs retrieved successfully.',
        );
    }
}
