<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\UpdateProgramCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\UpdateProgramRequest;
use App\Features\Programs\Catalog\UseCases\UpdateProgramUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateProgramController
{
    use ApiResponse;

    public function __invoke(UpdateProgramRequest $request, UpdateProgramUseCase $useCase): JsonResponse
    {
        $program = $useCase->execute(UpdateProgramCommand::fromRequest($request));

        return $this->success(
            $program->toArray(),
            'Program updated successfully.',
        );
    }
}
