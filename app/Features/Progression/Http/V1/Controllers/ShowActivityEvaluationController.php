<?php

declare(strict_types=1);

namespace App\Features\Progression\Http\V1\Controllers;

use App\Features\Progression\Http\V1\Commands\ShowActivityEvaluationCommand;
use App\Features\Progression\Http\V1\Requests\ShowActivityEvaluationRequest;
use App\Features\Progression\UseCases\ShowActivityEvaluationUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowActivityEvaluationController
{
    use ApiResponse;

    public function __invoke(
        ShowActivityEvaluationRequest $request,
        ShowActivityEvaluationUseCase $useCase,
    ): JsonResponse {
        $evaluation = $useCase->execute(ShowActivityEvaluationCommand::fromRequest($request));

        return $this->success(
            $evaluation->toArray(),
            'Activity evaluation retrieved successfully.',
        );
    }
}
