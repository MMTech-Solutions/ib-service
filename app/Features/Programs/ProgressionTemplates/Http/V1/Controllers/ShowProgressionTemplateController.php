<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\ShowProgressionTemplateUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ShowProgressionTemplateController
{
    use ApiResponse;

    public function __invoke(ManageProgressionTemplateRequest $request, string $progressionTemplate, ShowProgressionTemplateUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($progressionTemplate)->toArray(), 'Progression template retrieved successfully.');
    }
}
