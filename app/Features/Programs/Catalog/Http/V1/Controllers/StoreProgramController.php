<?php

declare(strict_types=1);

namespace App\Features\Programs\Catalog\Http\V1\Controllers;

use App\Features\Programs\Catalog\Http\V1\Commands\StoreProgramCommand;
use App\Features\Programs\Catalog\Http\V1\Requests\StoreProgramRequest;
use App\Features\Programs\Catalog\UseCases\StoreProgramUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class StoreProgramController
{
    use ApiResponse;

    public function __invoke(StoreProgramRequest $request, StoreProgramUseCase $useCase): JsonResponse
    {
        $program = $useCase->execute(StoreProgramCommand::fromRequest($request));

        return $this->created(
            $program->toArray(),
            'Program created successfully.',
        );
    }
}
