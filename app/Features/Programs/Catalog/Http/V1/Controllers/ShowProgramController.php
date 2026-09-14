<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\ShowProgramCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\ShowProgramRequest;
use App\Features\Programs\Catalog\UseCases\ShowProgramUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowProgramController
{
    use ApiResponse;

    public function __invoke(ShowProgramRequest $request, ShowProgramUseCase $useCase): JsonResponse
    {
        $program = $useCase->execute(ShowProgramCommand::fromRequest($request));

        return $this->success(
            $program->toArray(),
            'Program retrieved successfully.',
        );
    }
}
