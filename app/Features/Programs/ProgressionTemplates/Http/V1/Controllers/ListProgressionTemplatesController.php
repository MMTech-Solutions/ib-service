<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\ListProgressionTemplatesUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class ListProgressionTemplatesController
{
    use ApiResponse;

    public function __invoke(ManageProgressionTemplateRequest $request, ListProgressionTemplatesUseCase $useCase): JsonResponse
    {
        return $this->success(array_map(static fn ($template): array => $template->toArray(), $useCase->execute()), 'Progression templates retrieved successfully.');
    }
}
