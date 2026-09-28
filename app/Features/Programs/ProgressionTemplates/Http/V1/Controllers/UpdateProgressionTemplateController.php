<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Http\V1\Controllers;

use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\Features\Programs\ProgressionTemplates\Http\V1\Requests\ManageProgressionTemplateRequest;
use App\Features\Programs\ProgressionTemplates\UseCases\UpdateProgressionTemplateUseCase;
use Illuminate\Http\JsonResponse;
use MMT\ApiResponseNormalizer\ApiResponse;

final class UpdateProgressionTemplateController
{
    use ApiResponse;

    public function __invoke(ManageProgressionTemplateRequest $request, string $progressionTemplate, UpdateProgressionTemplateUseCase $useCase): JsonResponse
    {
        return $this->success($useCase->execute($progressionTemplate, ManageProgressionTemplateCommand::fromRequest($request))->toArray(), 'Progression template updated successfully.');
    }
}
