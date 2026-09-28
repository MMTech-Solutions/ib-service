<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\PublishProgressionTemplateVersionUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class PublishProgressionTemplateVersionController
{
    use ApiResponse;

    public function __invoke(ManageProgressionTemplateRequest $request, string $progressionTemplate, string $version, PublishProgressionTemplateVersionUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($progressionTemplate, $version, (int) $request->validated('lock_version'))->toArray(), 'Progression template version published successfully.');
    }
}
